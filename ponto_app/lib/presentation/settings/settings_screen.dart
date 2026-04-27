import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../core/constants/app_constants.dart';
import '../../core/settings/theme_mode_provider.dart';
import '../../core/theme/app_theme.dart';
import '../../services/biometric_service.dart';
import '../../services/face_service.dart';
import '../auth/auth_provider.dart';
import '../../data/models/user_model.dart' show UserModel;

class SettingsScreen extends ConsumerStatefulWidget {
  const SettingsScreen({super.key});

  @override
  ConsumerState<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends ConsumerState<SettingsScreen> {
  final _bio = BiometricService();
  bool _biometricOn = false;
  bool? _bioAvailable;
  bool _savingBiometric = false;
  bool _faceResetting = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final prefs = await SharedPreferences.getInstance();
    final available = await _bio.isAvailable();
    if (!mounted) return;
    setState(() {
      _biometricOn = prefs.getBool(AppConstants.biometricUnlockKey) ?? false;
      _bioAvailable = available;
    });
  }

  Future<void> _handleFaceTap(bool enrolled) async {
    if (enrolled) {
      final ok = await showDialog<bool>(
        context: context,
        builder: (_) => AlertDialog(
          title: const Text('Redefinir cadastro facial'),
          content: const Text(
            'O rosto atual será removido e você precisará cadastrar um novo. Continuar?',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Cancelar'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('Redefinir'),
            ),
          ],
        ),
      );
      if (ok != true || !mounted) return;
      setState(() => _faceResetting = true);
      try {
        await ref.read(faceServiceProvider).deleteEnroll();
        final user = ref.read(authProvider).user;
        if (user?.employee != null) {
          final emp = user!.employee!.copyWith(faceEnrolled: false);
          ref.read(authProvider.notifier).updateUser(
                UserModel(
                  id: user.id,
                  name: user.name,
                  email: user.email,
                  role: user.role,
                  active: user.active,
                  companyId: user.companyId,
                  company: user.company,
                  employee: emp,
                ),
              );
        }
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Cadastro facial removido.')),
          );
        }
      } catch (_) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Erro ao remover cadastro.')),
          );
        }
      } finally {
        if (mounted) setState(() => _faceResetting = false);
      }
    } else {
      if (mounted) context.push('/face-enroll');
    }
  }

  Future<void> _setBiometric(bool value) async {
    if (_savingBiometric) return;
    if (value) {
      final ok = await _bio.isAvailable();
      if (!ok) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Biometria ou PIN do dispositivo não disponível.')),
          );
        }
        return;
      }
      final authOk = await _bio.authenticate(reason: 'Ative o desbloqueio biométrico');
      if (!authOk) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Confirmação necessária para ativar a biometria.')),
          );
        }
        return;
      }
    }
    setState(() => _savingBiometric = true);
    final prefs = await SharedPreferences.getInstance();
    if (value) {
      await prefs.setBool(AppConstants.biometricUnlockKey, true);
    } else {
      await prefs.remove(AppConstants.biometricUnlockKey);
    }
    if (mounted) {
      setState(() {
        _biometricOn = value;
        _savingBiometric = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final mode = ref.watch(themeModeProvider);
    final subtle = Theme.of(context).colorScheme.onSurfaceVariant;

    return Scaffold(
      appBar: AppBar(title: const Text('Configurações')),
      body: ListView(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
        children: [
          const ListTile(
            title: Text('Aparência', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 15)),
            subtitle: Text('Tema do aplicativo'),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 8),
            child: SegmentedButton<ThemeMode>(
              segments: const [
                ButtonSegment(value: ThemeMode.system, label: Text('Sistema'), icon: Icon(Icons.brightness_auto, size: 18)),
                ButtonSegment(value: ThemeMode.light, label: Text('Claro'), icon: Icon(Icons.light_mode, size: 18)),
                ButtonSegment(value: ThemeMode.dark, label: Text('Escuro'), icon: Icon(Icons.dark_mode, size: 18)),
              ],
              selected: {mode},
              onSelectionChanged: (s) {
                if (s.isNotEmpty) {
                  ref.read(themeModeProvider.notifier).setMode(s.first);
                }
              },
            ),
          ),
          const Divider(),
          const ListTile(
            title: Text('Segurança', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 15)),
            subtitle: Text('Desbloquear ao reabrir o app'),
          ),
          SwitchListTile(
            title: const Text('Biometria'),
            subtitle: Text(
              _bioAvailable == null
                  ? 'Verificando...'
                  : _bioAvailable == false
                      ? 'Não disponível neste dispositivo'
                      : 'Exige leitor biométrico ou PIN do aparelho ao reabrir',
              style: TextStyle(fontSize: 13, color: subtle),
            ),
            value: _biometricOn && (_bioAvailable ?? false),
            onChanged: _bioAvailable == false || _savingBiometric
                ? null
                : (v) => _setBiometric(v),
          ),
          const Divider(),
          const ListTile(
            title: Text('Reconhecimento facial', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 15)),
            subtitle: Text('Verificação de identidade ao bater ponto'),
          ),
          Builder(builder: (context) {
            final user = ref.watch(authProvider).user;
            final enrolled = user?.employee?.faceEnrolled ?? false;
            return ListTile(
              leading: Icon(
                enrolled ? Icons.face : Icons.face_retouching_off,
                color: enrolled ? AppColors.success : AppColors.textSecondary,
              ),
              title: Text(enrolled ? 'Rosto cadastrado' : 'Rosto não cadastrado'),
              subtitle: Text(
                enrolled
                    ? 'Toque para redefinir o cadastro facial.'
                    : 'Toque para cadastrar o seu rosto.',
                style: TextStyle(fontSize: 13, color: subtle),
              ),
              trailing: _faceResetting
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.chevron_right),
              onTap: _faceResetting ? null : () => _handleFaceTap(enrolled),
            );
          }),
          const Divider(),
          ListTile(
            leading: const Icon(Icons.notifications_outlined),
            title: const Text('Notificações', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 15)),
            subtitle: Text(
              'Notificações push activas via Firebase Cloud Messaging. '
              'Recebe alertas de folgas aprovadas/rejeitadas e correções de ponto.',
              style: TextStyle(fontSize: 13, color: subtle),
            ),
            isThreeLine: true,
          ),
          const SizedBox(height: 8),
          ListTile(
            title: const Text('Encerrar sessão'),
            leading: const Icon(Icons.logout, color: AppColors.error),
            onTap: () async {
              await ref.read(authProvider.notifier).logout();
              if (context.mounted) {
                // go_router trata o redirect
              }
            },
          ),
        ],
      ),
    );
  }
}
