import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../services/biometric_service.dart';
import '../../core/theme/app_theme.dart';
import 'auth_provider.dart';

class UnlockScreen extends ConsumerStatefulWidget {
  const UnlockScreen({super.key});

  @override
  ConsumerState<UnlockScreen> createState() => _UnlockScreenState();
}

class _UnlockScreenState extends ConsumerState<UnlockScreen> {
  final _bio = BiometricService();
  bool _loading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _tryUnlock());
  }

  Future<void> _tryUnlock() async {
    if (_loading) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    final ok = await _bio.authenticate();
    if (!mounted) return;
    if (ok) {
      ref.read(authProvider.notifier).completeBiometricUnlock();
      context.go('/home');
    } else {
      setState(() {
        _loading = false;
        _error = 'Não foi possível validar. Tente novamente ou saia e entre com e-mail e senha.';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.fingerprint, size: 72, color: AppColors.primary),
              const SizedBox(height: 24),
              const Text(
                'Desbloquear Ponto Digital',
                style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 12),
              Text(
                _error ?? 'Use biometria ou o sensor do dispositivo para continuar.',
                textAlign: TextAlign.center,
                style: TextStyle(color: _error != null ? AppColors.error : AppColors.textSecondary),
              ),
              const SizedBox(height: 32),
              FilledButton.icon(
                onPressed: _loading ? null : _tryUnlock,
                icon: _loading
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                      )
                    : const Icon(Icons.fingerprint),
                label: Text(_loading ? 'Aguarde...' : 'Tentar novamente'),
              ),
              const SizedBox(height: 16),
              TextButton(
                onPressed: _loading
                    ? null
                    : () async {
                        await ref.read(authProvider.notifier).logout();
                        if (context.mounted) context.go('/login');
                      },
                child: const Text('Sair e usar senha'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
