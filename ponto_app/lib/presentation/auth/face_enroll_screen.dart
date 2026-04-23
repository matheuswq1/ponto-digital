import 'dart:io';
import 'package:camera/camera.dart';
import '../../core/utils/safe_camera_dispose.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:permission_handler/permission_handler.dart';
import '../../core/theme/app_theme.dart';
import '../../services/face_service.dart';
import '../../data/models/user_model.dart';
import 'auth_provider.dart';

enum _EnrollStep { camera, preview, loading, done, error }

class FaceEnrollScreen extends ConsumerStatefulWidget {
  /// Se não nulo, após o enroll oferece opção de voltar ao registo de ponto.
  final String? returnPointType;

  const FaceEnrollScreen({super.key, this.returnPointType});

  @override
  ConsumerState<FaceEnrollScreen> createState() => _FaceEnrollScreenState();
}

class _FaceEnrollScreenState extends ConsumerState<FaceEnrollScreen> {
  CameraController? _cam;
  List<CameraDescription>? _cameras;
  bool _camReady = false;
  File? _photo;
  _EnrollStep _step = _EnrollStep.camera;
  String? _error;

  @override
  void initState() {
    super.initState();
    _initCamera();
  }

  @override
  void dispose() {
    final c = _cam;
    _cam = null;
    scheduleDisposeCamera(c);
    super.dispose();
  }

  Future<void> _initCamera() async {
    final status = await Permission.camera.request();
    if (!status.isGranted) {
      setState(() {
        _camReady = false;
        _error = 'Permissão de câmera necessária para o cadastro facial.';
        _step = _EnrollStep.error;
      });
      return;
    }
    _cameras = await availableCameras();
    final front = _cameras?.firstWhere(
      (c) => c.lensDirection == CameraLensDirection.front,
      orElse: () => _cameras!.first,
    );
    if (front == null) {
      setState(() {
        _error = 'Câmera frontal não encontrada.';
        _step = _EnrollStep.error;
      });
      return;
    }
    _cam = CameraController(front, ResolutionPreset.high, enableAudio: false);
    await _cam!.initialize();
    if (mounted) setState(() => _camReady = true);
  }

  Future<void> _capture() async {
    if (_cam == null || !_cam!.value.isInitialized) return;
    final xf = await _cam!.takePicture();
    setState(() {
      _photo = File(xf.path);
      _step = _EnrollStep.preview;
    });
  }

  Future<void> _enroll() async {
    if (_photo == null) return;
    setState(() => _step = _EnrollStep.loading);

    try {
      await ref.read(faceServiceProvider).enroll(_photo!);

      final user = ref.read(authProvider).user;
      if (user?.employee != null) {
        final updatedEmployee = user!.employee!.copyWith(faceEnrolled: true);
        final updatedUser = _updatedUser(user, updatedEmployee);
        ref.read(authProvider.notifier).updateUser(updatedUser);
      }

      setState(() => _step = _EnrollStep.done);
    } catch (e) {
      setState(() {
        _step = _EnrollStep.error;
        _error = _friendlyError(e.toString());
      });
    }
  }

  UserModel _updatedUser(UserModel user, EmployeeModel emp) => UserModel(
        id: user.id,
        name: user.name,
        email: user.email,
        role: user.role,
        active: user.active,
        companyId: user.companyId,
        company: user.company,
        employee: emp,
      );

  String _friendlyError(String raw) {
    if (raw.contains('Nenhum rosto')) {
      return 'Nenhum rosto detectado. Centre o seu rosto na câmera e tente novamente.';
    }
    if (raw.contains('Mais de um rosto')) {
      return 'Mais de um rosto detectado. Certifique-se de estar sozinho.';
    }
    if (raw.contains('401')) return 'Serviço de reconhecimento não autorizado.';
    if (raw.contains('connect')) return 'Serviço de reconhecimento facial offline.';
    return 'Erro ao cadastrar rosto. Tente novamente.';
  }

  @override
  Widget build(BuildContext context) {
    final c = Theme.of(context).colorScheme;

    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        title: const Text('Cadastro Facial'),
        leading: _step == _EnrollStep.loading
            ? null
            : IconButton(
                icon: const Icon(Icons.arrow_back_ios),
                onPressed: () {
                  if (_step == _EnrollStep.preview) {
                    setState(() {
                      _photo = null;
                      _step = _EnrollStep.camera;
                    });
                  } else {
                    context.go('/home');
                  }
                },
              ),
      ),
      body: _buildBody(c),
    );
  }

  Widget _buildBody(ColorScheme c) {
    return switch (_step) {
      _EnrollStep.done => _buildDone(c),
      _EnrollStep.error => _buildError(),
      _EnrollStep.loading => _buildLoading(),
      _EnrollStep.preview => _buildPreview(c),
      _ => _buildCamera(c),
    };
  }

  Widget _buildCamera(ColorScheme c) {
    if (!_camReady || _cam == null) {
      return const Center(
        child: CircularProgressIndicator(color: Colors.white),
      );
    }

    return Stack(
      children: [
        SizedBox.expand(child: CameraPreview(_cam!)),
        // Guia oval
        Center(
          child: Container(
            width: 230,
            height: 290,
            decoration: BoxDecoration(
              border: Border.all(color: Colors.white70, width: 2.5),
              borderRadius: BorderRadius.circular(140),
            ),
          ),
        ),
        // Instrução
        Positioned(
          bottom: 130,
          left: 24,
          right: 24,
          child: Text(
            'Posicione o seu rosto dentro do oval.\nOlhe para a câmera com boa iluminação.',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: Colors.white.withValues(alpha: 0.9),
              fontSize: 14,
            ),
          ),
        ),
        // Botão de captura
        Positioned(
          bottom: 48,
          left: 0,
          right: 0,
          child: Center(
            child: GestureDetector(
              onTap: _capture,
              child: Container(
                width: 72,
                height: 72,
                decoration: const BoxDecoration(
                  color: Colors.white,
                  shape: BoxShape.circle,
                ),
                child: Icon(Icons.camera_alt, color: c.primary, size: 32),
              ),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildPreview(ColorScheme c) {
    return Column(
      children: [
        Expanded(
          child: Stack(
            children: [
              SizedBox.expand(
                child: Image.file(_photo!, fit: BoxFit.cover),
              ),
              Center(
                child: Container(
                  width: 230,
                  height: 290,
                  decoration: BoxDecoration(
                    border: Border.all(color: Colors.white70, width: 2.5),
                    borderRadius: BorderRadius.circular(140),
                  ),
                ),
              ),
            ],
          ),
        ),
        Container(
          color: Colors.black,
          padding: const EdgeInsets.fromLTRB(24, 16, 24, 40),
          child: Column(
            children: [
              const Text(
                'O rosto está bem visível e centrado?',
                style: TextStyle(color: Colors.white, fontSize: 16),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 20),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () => setState(() {
                        _photo = null;
                        _step = _EnrollStep.camera;
                      }),
                      icon: const Icon(Icons.refresh, color: Colors.white70),
                      label: const Text('Refazer', style: TextStyle(color: Colors.white70)),
                      style: OutlinedButton.styleFrom(
                        side: const BorderSide(color: Colors.white30),
                        minimumSize: const Size.fromHeight(50),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: FilledButton.icon(
                      onPressed: _enroll,
                      icon: const Icon(Icons.check),
                      label: const Text('Cadastrar'),
                      style: FilledButton.styleFrom(
                        minimumSize: const Size.fromHeight(50),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildLoading() {
    return const Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          CircularProgressIndicator(color: Colors.white),
          SizedBox(height: 20),
          Text(
            'Analisando e cadastrando rosto...',
            style: TextStyle(color: Colors.white, fontSize: 16),
          ),
        ],
      ),
    );
  }

  Widget _buildDone(ColorScheme c) {
    final returnType = widget.returnPointType;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 100,
              height: 100,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: c.primaryContainer,
              ),
              child: Icon(Icons.face, size: 56, color: c.primary),
            ),
            const SizedBox(height: 24),
            const Text(
              'Rosto cadastrado!',
              style: TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 12),
            Text(
              'A partir de agora o app vai verificar seu rosto ao bater ponto.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.white.withValues(alpha: 0.75), fontSize: 14),
            ),
            const SizedBox(height: 40),
            // Se veio de uma tentativa de registo de ponto, oferece voltar
            if (returnType != null) ...[
              FilledButton.icon(
                onPressed: () {
                  context.go('/home');
                  context.push('/home/register-point', extra: returnType);
                },
                icon: const Icon(Icons.fingerprint),
                label: const Text('Bater ponto agora'),
                style: FilledButton.styleFrom(
                  minimumSize: const Size(double.infinity, 50),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
              ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: () => context.go('/home'),
                icon: const Icon(Icons.home, color: Colors.white70),
                label: const Text('Ir para o início', style: TextStyle(color: Colors.white70)),
                style: OutlinedButton.styleFrom(
                  side: const BorderSide(color: Colors.white30),
                  minimumSize: const Size(double.infinity, 50),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
              ),
            ] else
              FilledButton.icon(
                onPressed: () => context.go('/home'),
                icon: const Icon(Icons.home),
                label: const Text('Ir para o início'),
                style: FilledButton.styleFrom(
                  minimumSize: const Size(200, 50),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildError() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, color: AppColors.error, size: 64),
            const SizedBox(height: 20),
            Text(
              _error ?? 'Erro desconhecido.',
              style: const TextStyle(color: Colors.white, fontSize: 15),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 32),
            FilledButton.icon(
              onPressed: () {
                setState(() {
                  _step = _EnrollStep.camera;
                  _error = null;
                  _photo = null;
                });
                _initCamera();
              },
              icon: const Icon(Icons.refresh),
              label: const Text('Tentar novamente'),
            ),
            const SizedBox(height: 12),
            TextButton(
              onPressed: () => context.go('/home'),
              child: const Text('Pular por agora', style: TextStyle(color: Colors.white60)),
            ),
          ],
        ),
      ),
    );
  }
}
