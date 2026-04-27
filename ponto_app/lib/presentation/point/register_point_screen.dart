import 'dart:io';
import 'package:camera/camera.dart';
import '../../core/utils/safe_camera_dispose.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:url_launcher/url_launcher.dart';
import 'register_point_provider.dart' show registerPointProvider, RegisterPointStatus, PolicyCheckResult;
import '../home/today_provider.dart';
import '../auth/auth_provider.dart';
import '../../core/theme/app_theme.dart';
import '../../core/constants/app_constants.dart';
import '../../data/models/user_model.dart';
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
  bool _cameraReady = false;
  bool _useFrontCamera = true;
  bool _verifyingFace = false; // true durante a chamada /face/verify
  bool _capturing = false;     // true durante takePicture — feedback imediato

  @override
  void initState() {
    super.initState();
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

  /// Captura foto (se câmera disponível) e regista o ponto de uma só vez.
  Future<void> _captureAndRegister() async {
    if (ref.read(registerPointProvider).isLoading || _verifyingFace || _capturing) return;

    // Feedback imediato — escurece o botão antes mesmo da foto ser tirada
    setState(() => _capturing = true);

    File? photo;
    if (_cameraReady && _cameraController != null &&
        _cameraController!.value.isInitialized) {
      try {
        final xFile = await _cameraController!.takePicture();
        photo = File(xFile.path);
      } catch (_) {}
    }

    if (mounted) setState(() => _capturing = false);
    await _confirmRegister(photo: photo);
  }

  Future<void> _confirmRegister({File? photo}) async {
    final authState = ref.read(authProvider);
    final faceEnrolled = authState.user?.employee?.faceEnrolled ?? false;
    final company = authState.user?.employee?.company ?? authState.user?.company;

    // ── Verificar políticas da empresa ─────────────────────────────
    final notifier = ref.read(registerPointProvider.notifier);
    final policy = await notifier.checkCompanyPolicy(
      company: company,
      photo: photo,
    );

    if (!mounted) return;

    if (policy == PolicyCheckResult.photoRequired) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
        content: Text('Sua empresa exige foto para bater o ponto. Tire uma selfie antes.'),
        backgroundColor: AppColors.error,
      ));
      return;
    }

    if (policy == PolicyCheckResult.geofenceViolation) {
      _showGeofenceDialog(company);
      return;
    }

    if (policy == PolicyCheckResult.geofenceUnavailable) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
        content: Text('Não foi possível obter sua localização. Habilite o GPS e tente novamente.'),
        backgroundColor: AppColors.warning,
      ));
      return;
    }

    if (policy == PolicyCheckResult.mockBlocked) {
      _showFraudBlockedDialog(
        title: 'GPS Falso Detectado',
        message:
            'Foi detectado um app de GPS falso activo no seu dispositivo.\n\nDesactive o Mock Location e tente novamente.',
        icon: Icons.gps_off_rounded,
      );
      return;
    }

    if (policy == PolicyCheckResult.wifiMismatch) {
      _showFraudBlockedDialog(
        title: 'Wi-Fi não autorizado',
        message:
            'A sua empresa exige que esteja ligado a uma rede Wi-Fi específica para bater o ponto.\n\nConecte-se à rede da empresa e tente novamente.',
        icon: Icons.wifi_off_rounded,
      );
      return;
    }
    // ───────────────────────────────────────────────────────────────

    if (!faceEnrolled) {
      _showEnrollRequiredDialog();
      return;
    }

    // Se não há foto capturada, não é possível verificar o rosto
    if (photo == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
        content: Text('É necessário a câmera para verificar a identidade.'),
        backgroundColor: AppColors.warning,
      ));
      return;
    }

    // Verificar identidade com a foto já capturada — sem abrir nova câmera
    setState(() => _verifyingFace = true);
    final faceService = ref.read(faceServiceProvider);
    final faceResult = await faceService.verify(photo);
    if (mounted) setState(() => _verifyingFace = false);

    if (!mounted) return;

    if (!faceResult.faceEnrolled) {
      _showEnrollRequiredDialog();
      return;
    }

    if (!faceResult.match) {
      _showFaceFailDialog();
      return;
    }

    final success = await notifier.register(widget.pointType, photo: photo);

    if (!mounted) return;

    final state = ref.read(registerPointProvider);
    if (success) {
      _showSuccessDialog(state.status == RegisterPointStatus.offline);
    }
  }

  void _showFraudBlockedDialog({
    required String title,
    required String message,
    required IconData icon,
  }) {
    showDialog<void>(
      context: context,
      builder: (_) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Row(
          children: [
            Icon(icon, color: AppColors.error),
            const SizedBox(width: 10),
            Expanded(child: Text(title, style: const TextStyle(fontSize: 16))),
          ],
        ),
        content: Text(message),
        actions: [
          ElevatedButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Entendi'),
          ),
        ],
      ),
    );
  }

  void _showGeofenceDialog(CompanyModel? company) {
    // Pegar todas as geocercas activas (novo sistema) ou a legada
    final locations = company?.geofences ?? [];
    final legacyGeofence = company?.geofence;

    // Montar lista de locais permitidos para exibir
    final allowedPlaces = <_AllowedPlace>[];
    for (final loc in locations) {
      allowedPlaces.add(_AllowedPlace(
        name: loc.name,
        address: loc.address,
        lat: loc.latitude,
        lng: loc.longitude,
        radiusMeters: loc.radiusMeters,
      ));
    }
    if (allowedPlaces.isEmpty &&
        legacyGeofence?.enabled == true &&
        legacyGeofence?.latitude != null &&
        legacyGeofence?.longitude != null) {
      allowedPlaces.add(_AllowedPlace(
        name: company?.name ?? 'Empresa',
        address: null,
        lat: legacyGeofence!.latitude!,
        lng: legacyGeofence.longitude!,
        radiusMeters: legacyGeofence.radiusMeters,
      ));
    }

    showDialog<void>(
      context: context,
      builder: (_) => _GeofenceViolationDialog(
        companyName: company?.name ?? 'sua empresa',
        allowedPlaces: allowedPlaces,
      ),
    );
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
              child: Text('Cadastro facial necessário', style: TextStyle(fontSize: 16)),
            ),
          ],
        ),
        content: const Text(
          'Para bater o ponto é obrigatório ter o rosto cadastrado.\n\nDeseja cadastrar agora?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Agora não'),
          ),
          ElevatedButton.icon(
            onPressed: () {
              Navigator.pop(context);
              context.push('/face-enroll', extra: {'returnPointType': widget.pointType});
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
          'O ponto não foi registrado.',
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
    final company = ref.read(authProvider).user?.employee?.company ??
        ref.read(authProvider).user?.company;
    final requirePhoto = company?.requirePhoto ?? false;

    // Diâmetro do círculo igual ao cálculo usado em _buildCameraWithCircle
    final screenWidth = MediaQuery.of(context).size.width;
    final circleDiameter = screenWidth * 0.72;

    return Scaffold(
      backgroundColor: const Color(0xFF0A0F1E),
      appBar: AppBar(
        backgroundColor: const Color(0xFF0A0F1E),
        foregroundColor: Colors.white,
        title: Text('Registrar — $label', style: const TextStyle(fontSize: 16)),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios),
          onPressed: state.isLoading ? null : () => context.pop(),
        ),
      ),
      body: SafeArea(
        child: Column(
          children: [
            // ── Área da câmera ──────────────────────────────────────────────
            Expanded(
              child: Stack(
                alignment: Alignment.center,
                children: [
                  // Fundo sólido (sem câmera a sangrar para fora do círculo)
                  Container(color: const Color(0xFF0A0F1E)),

                  // Círculo com câmera dentro (ClipOval) + borda
                  if (_cameraReady && _cameraController != null)
                    _buildCameraWithCircle(circleDiameter)
                  else
                    Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Container(
                          width: circleDiameter,
                          height: circleDiameter,
                          decoration: const BoxDecoration(
                            shape: BoxShape.circle,
                            color: Color(0xFF1A2035),
                          ),
                          child: Icon(
                            Icons.camera_alt,
                            color: Colors.white.withValues(alpha: 0.25),
                            size: 64,
                          ),
                        ),
                        const SizedBox(height: 20),
                        const Text(
                          'Câmera não disponível\nO ponto será registrado sem foto.',
                          textAlign: TextAlign.center,
                          style: TextStyle(color: Colors.white54, fontSize: 14),
                        ),
                      ],
                    ),

                  // Texto de instrução — centralizado abaixo do círculo
                  if (!state.isLoading)
                    Positioned(
                      bottom: 12,
                      left: 0,
                      right: 0,
                      child: Column(
                        children: [
                          Text(
                            'Posicione seu rosto no círculo',
                            textAlign: TextAlign.center,
                            style: TextStyle(
                              color: Colors.white.withValues(alpha: 0.45),
                              fontSize: 13,
                            ),
                          ),
                          if (requirePhoto) ...[
                            const SizedBox(height: 4),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Icon(Icons.camera_alt,
                                    color: AppColors.warning.withValues(alpha: 0.8),
                                    size: 11),
                                const SizedBox(width: 4),
                                Text(
                                  'Foto obrigatória',
                                  style: TextStyle(
                                    color: AppColors.warning.withValues(alpha: 0.8),
                                    fontSize: 11,
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ],
                      ),
                    ),

                  // Loading overlay — verificação facial OU envio do ponto
                  if (_verifyingFace || state.isLoading)
                    Container(
                      color: const Color(0xFF0A0F1E),
                      child: Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            const CircularProgressIndicator(color: Colors.white),
                            const SizedBox(height: 20),
                            Text(
                              _verifyingFace
                                  ? 'Verificando identidade...'
                                  : _loadingMessage(state.status),
                              style: const TextStyle(color: Colors.white, fontSize: 16),
                            ),
                            const SizedBox(height: 8),
                            Text(
                              _verifyingFace
                                  ? 'Aguarde um momento'
                                  : '',
                              style: const TextStyle(color: Colors.white38, fontSize: 13),
                            ),
                          ],
                        ),
                      ),
                    ),

                  // Erro
                  if (state.status == RegisterPointStatus.error)
                    Positioned(
                      bottom: 12,
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

                  // Flip câmera (oculto durante verificação/carregamento)
                  if (_cameraReady && !_verifyingFace && !state.isLoading)
                    Positioned(
                      top: 12,
                      right: 12,
                      child: IconButton(
                        icon: const Icon(Icons.flip_camera_android, color: Colors.white70),
                        onPressed: _flipCamera,
                      ),
                    ),

                  // GPS falso
                  if (state.isMock)
                    Positioned(
                      top: 12,
                      left: 12,
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
                            Text('GPS Falso',
                                style: TextStyle(color: Colors.white, fontSize: 12)),
                          ],
                        ),
                      ),
                    ),
                ],
              ),
            ),

            // ── Área inferior fixa ───────────────────────────────────────────
            // Usa MediaQuery.padding.bottom para nunca sobrepormos a barra do sistema
            Container(
              color: const Color(0xFF0A0F1E),
              padding: EdgeInsets.fromLTRB(
                24,
                16,
                24,
                16 + MediaQuery.of(context).padding.bottom,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  // Badge tipo de ponto
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                    decoration: BoxDecoration(
                      color: typeColor.withValues(alpha: 0.15),
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: typeColor.withValues(alpha: 0.4)),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.access_time, color: typeColor, size: 15),
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

                  // Botão circular — feedback imediato ao toque
                  GestureDetector(
                    onTap: (state.isLoading || _verifyingFace || _capturing) ? null : _captureAndRegister,
                    child: AnimatedContainer(
                      duration: const Duration(milliseconds: 120),
                      width: _capturing ? 72 : 80,
                      height: _capturing ? 72 : 80,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: (state.isLoading || _verifyingFace || _capturing)
                            ? typeColor.withValues(alpha: 0.45)
                            : typeColor,
                        boxShadow: (state.isLoading || _verifyingFace || _capturing)
                            ? []
                            : [
                                BoxShadow(
                                  color: typeColor.withValues(alpha: 0.5),
                                  blurRadius: 24,
                                  spreadRadius: 4,
                                ),
                              ],
                      ),
                      child: (state.isLoading || _verifyingFace || _capturing)
                          ? const Padding(
                              padding: EdgeInsets.all(22),
                              child: CircularProgressIndicator(
                                  color: Colors.white, strokeWidth: 2.5),
                            )
                          : const Icon(Icons.touch_app,
                              color: Colors.white, size: 38),
                    ),
                  ),
                  const SizedBox(height: 10),
                  Text(
                    _capturing
                        ? 'Capturando foto...'
                        : _verifyingFace
                            ? 'Verificando identidade...'
                            : state.isLoading
                                ? _loadingMessage(state.status)
                                : 'Toque para registrar',
                    style: const TextStyle(color: Colors.white54, fontSize: 13),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  /// Câmera contida no círculo — idêntico ao Totem (ClipOval + borda).
  /// O fundo fora do círculo é o próprio Container sólido do pai.
  Widget _buildCameraWithCircle(double circleDiameter) {
    final cam = _cameraController!;
    final aspect = 1.0 / cam.value.aspectRatio;

    return Container(
      width: circleDiameter,
      height: circleDiameter,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        border: Border.all(color: Colors.white24, width: 2),
      ),
      child: ClipOval(
        child: FittedBox(
          fit: BoxFit.cover,
          clipBehavior: Clip.hardEdge,
          child: SizedBox(
            width: cam.value.previewSize?.height ?? 480,
            height: cam.value.previewSize?.width ?? 640,
            child: AspectRatio(
              aspectRatio: aspect,
              child: CameraPreview(cam),
            ),
          ),
        ),
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
    _cameraController =
        CameraController(camera, ResolutionPreset.medium, enableAudio: false);
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

class _AllowedPlace {
  final String name;
  final String? address;
  final double lat;
  final double lng;
  final int radiusMeters;
  const _AllowedPlace({required this.name, required this.address, required this.lat, required this.lng, required this.radiusMeters});
}

class _GeofenceViolationDialog extends StatelessWidget {
  final String companyName;
  final List<_AllowedPlace> allowedPlaces;
  const _GeofenceViolationDialog({required this.companyName, required this.allowedPlaces});

  Future<void> _openMaps(_AllowedPlace place) async {
    final uri = Uri.parse('https://www.google.com/maps/search/?api=1&query=${place.lat},${place.lng}');
    if (await canLaunchUrl(uri)) await launchUrl(uri, mode: LaunchMode.externalApplication);
  }

  @override
  Widget build(BuildContext context) {
    final primary = allowedPlaces.isNotEmpty ? allowedPlaces.first : null;

    return Dialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      insetPadding: const EdgeInsets.symmetric(horizontal: 20),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // ── Cabeçalho com ícone de mapa ──────────────────────────────
            Container(
              width: double.infinity,
              color: AppColors.error,
              padding: const EdgeInsets.fromLTRB(16, 18, 16, 18),
              child: Column(
                children: [
                  const Icon(Icons.location_off_rounded, color: Colors.white, size: 40),
                  const SizedBox(height: 8),
                  const Text(
                    'Fora da área permitida',
                    style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16),
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Você precisa estar em uma das áreas de $companyName.',
                    style: const TextStyle(color: Colors.white70, fontSize: 12),
                    textAlign: TextAlign.center,
                  ),
                ],
              ),
            ),

            // ── Lista de locais permitidos ───────────────────────────────
            if (allowedPlaces.isNotEmpty)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Locais onde pode bater o ponto:',
                      style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF616161)),
                    ),
                    const SizedBox(height: 8),
                    ...allowedPlaces.map((p) => Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: InkWell(
                        onTap: () => _openMaps(p),
                        borderRadius: BorderRadius.circular(12),
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF5F5F5),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: const Color(0xFFE0E0E0)),
                          ),
                          child: Row(
                            children: [
                              Container(
                                width: 36, height: 36,
                                decoration: BoxDecoration(
                                  color: AppColors.primary.withValues(alpha: 0.1),
                                  shape: BoxShape.circle,
                                ),
                                child: const Icon(Icons.place, size: 20, color: AppColors.primary),
                              ),
                              const SizedBox(width: 10),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(p.name, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                                    if (p.address != null)
                                      Text(p.address!, style: const TextStyle(fontSize: 11, color: Color(0xFF9E9E9E))),
                                    Text('Raio: ${p.radiusMeters}m', style: const TextStyle(fontSize: 11, color: Color(0xFF9E9E9E))),
                                  ],
                                ),
                              ),
                              const Icon(Icons.chevron_right, size: 18, color: AppColors.primary),
                            ],
                          ),
                        ),
                      ),
                    )),
                  ],
                ),
              ),

            // ── Botões ───────────────────────────────────────────────────
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
              child: Row(
                children: [
                  if (primary != null)
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: () { Navigator.pop(context); _openMaps(primary); },
                        icon: const Icon(Icons.directions, size: 16),
                        label: const Text('Como chegar'),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: AppColors.primary,
                          side: const BorderSide(color: AppColors.primary),
                          padding: const EdgeInsets.symmetric(vertical: 11),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        ),
                      ),
                    ),
                  if (primary != null) const SizedBox(width: 10),
                  Expanded(
                    child: ElevatedButton(
                      onPressed: () => Navigator.pop(context),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.primary,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 11),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                      child: const Text('Entendi'),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
