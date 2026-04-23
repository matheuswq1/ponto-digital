import 'dart:io';
import 'package:camera/camera.dart';
import '../../core/utils/safe_camera_dispose.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:permission_handler/permission_handler.dart';
import 'register_point_provider.dart';
import 'face_verify_step.dart';
import '../home/today_provider.dart';
import '../auth/auth_provider.dart';
import '../../core/theme/app_theme.dart';
import '../../core/constants/app_constants.dart';
import '../../services/face_service.dart';

class RegisterPointScreen extends ConsumerStatefulWidget {
  final String pointType;

  const RegisterPointScreen({super.key, required this.pointType});

  @override
  ConsumerState<RegisterPointScreen> createState() => _RegisterPointScreenState();
}

class _RegisterPointScreenState extends ConsumerState<RegisterPointScreen> {
  CameraController? _cameraController;
  List<CameraDescription>? _cameras;
  File? _capturedPhoto;
  bool _cameraReady = false;
  bool _useFrontCamera = true;

  @override
  void initState() {
    super.initState();
    // reset() precisa ser chamado após o build para não modificar provider durante o build
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) ref.read(registerPointProvider.notifier).reset();
    });
    _initCamera();
  }

  Future<void> _initCamera() async {
    final cameraPermission = await Permission.camera.request();
    if (!cameraPermission.isGranted) {
      setState(() => _cameraReady = false);
      return;
    }

    _cameras = await availableCameras();
    if (_cameras == null || _cameras!.isEmpty) return;

    final camera = _cameras!.firstWhere(
      (c) => c.lensDirection == CameraLensDirection.front,
      orElse: () => _cameras!.first,
    );

    _cameraController = CameraController(
      camera,
      ResolutionPreset.medium,
      enableAudio: false,
      imageFormatGroup: ImageFormatGroup.jpeg,
    );

    await _cameraController!.initialize();
    if (mounted) setState(() => _cameraReady = true);
  }

  @override
  void dispose() {
    final c = _cameraController;
    _cameraController = null;
    scheduleDisposeCamera(c);
    super.dispose();
  }

  Future<void> _takePicture() async {
    if (_cameraController == null || !_cameraController!.value.isInitialized) return;
    try {
      final xFile = await _cameraController!.takePicture();
      setState(() => _capturedPhoto = File(xFile.path));
    } catch (e) {
      // Continua sem foto se houver erro
    }
  }

  Future<void> _confirmRegister() async {
    // Verifica se o utilizador já tem rosto cadastrado
    final authState = ref.read(authProvider);
    final faceEnrolled = authState.user?.employee?.faceEnrolled ?? false;

    if (!faceEnrolled) {
      // Sem cadastro facial → leva para enroll antes de bater ponto
      _showEnrollRequiredDialog();
      return;
    }

    // Etapa de verificação facial (sheet modal)
    // Retorno: true = ok, false = falha, null = sem cadastro (ir para enroll)
    final faceResult = await showDialog<bool?>(
      context: context,
      barrierDismissible: false,
      builder: (_) => Dialog.fullscreen(
        child: FaceVerifyStep(
          faceService: ref.read(faceServiceProvider),
        ),
      ),
    );

    if (!mounted) return;

    if (faceResult == null) {
      // Rosto não cadastrado detectado na verificação → enroll
      _showEnrollRequiredDialog();
      return;
    }

    if (faceResult == false) {
      // Falha confirmada → alerta e retorna sem registrar
      _showFaceFailDialog();
      return;
    }

    final notifier = ref.read(registerPointProvider.notifier);
    final success = await notifier.register(widget.pointType, photo: _capturedPhoto);

    if (!mounted) return;

    final state = ref.read(registerPointProvider);

    if (success) {
      _showSuccessDialog(state.status == RegisterPointStatus.offline);
    }
  }

  void _showEnrollRequiredDialog() {
    showDialog(
      context: context,
      builder: (_) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Row(
          children: [
            Icon(Icons.face_retouching_off, color: AppColors.warning),
            SizedBox(width: 10),
            Expanded(
              child: Text(
                'Cadastro facial necessário',
                style: TextStyle(fontSize: 16),
              ),
            ),
          ],
        ),
        content: const Text(
          'Para bater o ponto é obrigatório ter o rosto cadastrado.\n\n'
          'Deseja cadastrar agora?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Agora não'),
          ),
          ElevatedButton.icon(
            onPressed: () {
              Navigator.pop(context);
              context.push('/face-enroll',
                  extra: {'returnPointType': widget.pointType});
            },
            icon: const Icon(Icons.face, size: 18),
            label: const Text('Cadastrar rosto'),
          ),
        ],
      ),
    );
  }

  void _showFaceFailDialog() {
    showDialog(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Verificação facial falhou'),
        content: const Text(
          'O rosto detectado não corresponde ao colaborador cadastrado. '
          'O ponto não foi registrado. Entre em contato com o RH se for necessário.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Entendi'),
          ),
        ],
      ),
    );
  }

  void _showSuccessDialog(bool isOffline) {
    final label = AppConstants.pointTypeLabels[widget.pointType] ?? widget.pointType;

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (_) => Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        child: Padding(
          padding: const EdgeInsets.all(28),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 72,
                height: 72,
                decoration: BoxDecoration(
                  color: isOffline
                      ? AppColors.warning.withValues(alpha: 0.1)
                      : AppColors.success.withValues(alpha: 0.1),
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  isOffline ? Icons.wifi_off : Icons.check_circle,
                  size: 40,
                  color: isOffline ? AppColors.warning : AppColors.success,
                ),
              ),
              const SizedBox(height: 16),
              Text(
                isOffline ? 'Ponto salvo offline' : 'Ponto registrado!',
                style: const TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.bold,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                isOffline
                    ? 'Sem internet. O ponto de "$label" foi salvo e será sincronizado automaticamente.'
                    : 'Seu ponto de "$label" foi registrado com sucesso.',
                textAlign: TextAlign.center,
                style: const TextStyle(color: AppColors.textSecondary, fontSize: 14),
              ),
              const SizedBox(height: 24),
              ElevatedButton(
                onPressed: () {
                  Navigator.pop(context);
                  // Atualiza o status do dia antes de voltar para o home
                  ref.read(todayProvider.notifier).refresh();
                  context.go('/home');
                },
                child: const Text('Voltar ao início'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(registerPointProvider);
    final label = AppConstants.pointTypeLabels[widget.pointType] ?? widget.pointType;
    final typeColor = _typeColor(widget.pointType);

    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        title: Text(
          'Registrar — $label',
          style: const TextStyle(fontSize: 16),
        ),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios),
          onPressed: state.isLoading ? null : () => context.pop(),
        ),
      ),
      body: Column(
        children: [
          // Preview da câmera
          Expanded(
            child: Stack(
              children: [
                // Câmera ou foto capturada
                if (_capturedPhoto != null)
                  SizedBox.expand(
                    child: Image.file(_capturedPhoto!, fit: BoxFit.cover),
                  )
                else if (_cameraReady && _cameraController != null)
                  SizedBox.expand(
                    child: CameraPreview(_cameraController!),
                  )
                else
                  const Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.camera_alt, color: Colors.white54, size: 64),
                        SizedBox(height: 12),
                        Text(
                          'Câmera não disponível\nO ponto será registrado sem foto.',
                          textAlign: TextAlign.center,
                          style: TextStyle(color: Colors.white54),
                        ),
                      ],
                    ),
                  ),

                // Overlay de carregamento
                if (state.isLoading)
                  Container(
                    color: Colors.black.withValues(alpha: 0.6),
                    child: Center(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const CircularProgressIndicator(color: Colors.white),
                          const SizedBox(height: 16),
                          Text(
                            _loadingMessage(state.status),
                            style: const TextStyle(color: Colors.white, fontSize: 15),
                          ),
                        ],
                      ),
                    ),
                  ),

                // Erro
                if (state.status == RegisterPointStatus.error)
                  Positioned(
                    bottom: 20,
                    left: 20,
                    right: 20,
                    child: Container(
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: AppColors.error,
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Text(
                        state.errorMessage ?? 'Erro ao registrar ponto.',
                        style: const TextStyle(color: Colors.white),
                        textAlign: TextAlign.center,
                      ),
                    ),
                  ),

                // Botão de inverter câmera
                if (_capturedPhoto == null && _cameraReady)
                  Positioned(
                    top: 16,
                    right: 16,
                    child: IconButton(
                      icon: const Icon(Icons.flip_camera_android, color: Colors.white),
                      onPressed: () => _flipCamera(),
                    ),
                  ),

                // Indicador de mock location
                if (state.isMock)
                  Positioned(
                    top: 16,
                    left: 16,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                      decoration: BoxDecoration(
                        color: AppColors.error,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: const Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(Icons.warning_amber, color: Colors.white, size: 14),
                          SizedBox(width: 4),
                          Text('GPS Falso', style: TextStyle(color: Colors.white, fontSize: 12)),
                        ],
                      ),
                    ),
                  ),
              ],
            ),
          ),

          // Área inferior com botões
          Container(
            color: Colors.black,
            padding: const EdgeInsets.fromLTRB(24, 16, 24, 32),
            child: Column(
              children: [
                // Info do tipo
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  decoration: BoxDecoration(
                    color: typeColor.withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: typeColor.withValues(alpha: 0.4)),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.access_time, color: typeColor, size: 16),
                      const SizedBox(width: 6),
                      Text(
                        label,
                        style: TextStyle(
                          color: typeColor,
                          fontWeight: FontWeight.bold,
                          fontSize: 14,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 20),

                Row(
                  children: [
                    // Botão tirar/retirar foto
                    if (_capturedPhoto == null)
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: state.isLoading || !_cameraReady ? null : _takePicture,
                          icon: const Icon(Icons.camera_alt, color: Colors.white),
                          label: const Text('Tirar Selfie',
                              style: TextStyle(color: Colors.white)),
                          style: OutlinedButton.styleFrom(
                            side: const BorderSide(color: Colors.white54),
                            minimumSize: const Size.fromHeight(50),
                            shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12)),
                          ),
                        ),
                      )
                    else
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: state.isLoading
                              ? null
                              : () => setState(() => _capturedPhoto = null),
                          icon: const Icon(Icons.refresh, color: Colors.white70),
                          label: const Text('Refazer',
                              style: TextStyle(color: Colors.white70)),
                          style: OutlinedButton.styleFrom(
                            side: const BorderSide(color: Colors.white30),
                            minimumSize: const Size.fromHeight(50),
                            shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12)),
                          ),
                        ),
                      ),

                    const SizedBox(width: 12),

                    // Botão confirmar
                    Expanded(
                      child: ElevatedButton.icon(
                        onPressed: state.isLoading ? null : _confirmRegister,
                        icon: const Icon(Icons.check, color: Colors.white),
                        label: const Text('Confirmar'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: typeColor,
                          foregroundColor: Colors.white,
                          minimumSize: const Size.fromHeight(50),
                          shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12)),
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _flipCamera() async {
    if (_cameras == null || _cameras!.length < 2) return;
    _useFrontCamera = !_useFrontCamera;
    final direction =
        _useFrontCamera ? CameraLensDirection.front : CameraLensDirection.back;
    final camera = _cameras!.firstWhere(
      (c) => c.lensDirection == direction,
      orElse: () => _cameras!.first,
    );
    final old = _cameraController;
    _cameraController = null;
    await safeDisposeCamera(old);
    _cameraController = CameraController(camera, ResolutionPreset.medium, enableAudio: false);
    await _cameraController!.initialize();
    if (mounted) setState(() {});
  }

  String _loadingMessage(RegisterPointStatus status) {
    return switch (status) {
      RegisterPointStatus.loadingLocation => 'Obtendo localização GPS...',
      RegisterPointStatus.uploading => 'Enviando ponto...',
      _ => 'Processando...',
    };
  }

  Color _typeColor(String type) {
    return switch (type) {
      'entrada' => AppColors.entrada,
      'saida' => AppColors.saida,
      _ => AppColors.primary,
    };
  }
}

