import 'dart:async';
import 'dart:io';
import 'package:camera/camera.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/safe_camera_dispose.dart';
import '../../presentation/auth/auth_provider.dart';
import '../../services/totem_service.dart';

enum _TotemStep {
  idle,       // câmera ativa, aguardando rosto
  scanning,   // capturando e enviando para IA
  identified, // rosto reconhecido — mostra opções de ponto
  registering,// registrando o ponto
  success,    // ponto registrado — feedback positivo
  failed,     // rosto não reconhecido
}

class TotemScreen extends ConsumerStatefulWidget {
  const TotemScreen({super.key});

  @override
  ConsumerState<TotemScreen> createState() => _TotemScreenState();
}

class _TotemScreenState extends ConsumerState<TotemScreen> {
  CameraController? _cam;
  bool _camReady = false;
  _TotemStep _step = _TotemStep.idle;
  TotemIdentifyResult? _identified;
  TotemPointResult? _lastPoint;
  String? _errorMsg;
  Timer? _resetTimer;
  Timer? _clockTimer;
  String _clock = '';

  @override
  void initState() {
    super.initState();
    _startClock();
    _initCamera();
  }

  @override
  void dispose() {
    _resetTimer?.cancel();
    _clockTimer?.cancel();
    final c = _cam;
    _cam = null;
    scheduleDisposeCamera(c);
    super.dispose();
  }

  void _startClock() {
    _updateClock();
    _clockTimer = Timer.periodic(const Duration(seconds: 1), (_) => _updateClock());
  }

  void _updateClock() {
    if (!mounted) return;
    setState(() => _clock = DateFormat('HH:mm:ss').format(DateTime.now()));
  }

  Future<void> _initCamera() async {
    final cameras = await availableCameras();
    if (cameras.isEmpty) return;
    final front = cameras.firstWhere(
      (c) => c.lensDirection == CameraLensDirection.front,
      orElse: () => cameras.first,
    );
    _cam = CameraController(front, ResolutionPreset.medium, enableAudio: false);
    await _cam!.initialize();
    if (!mounted) return;
    setState(() => _camReady = true);
  }

  Future<void> _scan() async {
    if (_step != _TotemStep.idle || _cam == null || !_cam!.value.isInitialized) return;

    setState(() => _step = _TotemStep.scanning);

    try {
      final xf = await _cam!.takePicture();
      final result = await ref
          .read(totemServiceProvider)
          .identify(File(xf.path));

      if (!mounted) return;

      if (result.match && result.employeeId != null) {
        setState(() {
          _identified = result;
          _step = _TotemStep.identified;
        });
        // Auto-registro se só existe um tipo disponível (ex: após saída almoço → volta almoço)
        if (result.nextTypes.length == 1) {
          await _registerPoint(result.nextTypes.first);
        }
      } else {
        setState(() {
          _step = _TotemStep.failed;
          _errorMsg = result.message ?? 'Rosto não reconhecido.';
        });
        _scheduleReset(seconds: 3);
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _step = _TotemStep.failed;
          _errorMsg = 'Erro ao conectar. Tente novamente.';
        });
        _scheduleReset(seconds: 3);
      }
    }
  }

  Future<void> _registerPoint(String type) async {
    if (_identified == null) return;
    setState(() => _step = _TotemStep.registering);

    final result = await ref.read(totemServiceProvider).registerPoint(
          employeeId: _identified!.employeeId!,
          type: type,
        );

    if (!mounted) return;

    if (result != null) {
      setState(() {
        _lastPoint = result;
        _step = _TotemStep.success;
      });
      _scheduleReset(seconds: 5);
    } else {
      setState(() {
        _step = _TotemStep.failed;
        _errorMsg = 'Não foi possível registrar o ponto. Tente novamente.';
      });
      _scheduleReset(seconds: 4);
    }
  }

  void _scheduleReset({int seconds = 4}) {
    _resetTimer?.cancel();
    _resetTimer = Timer(Duration(seconds: seconds), () {
      if (mounted) setState(() => _step = _TotemStep.idle);
    });
  }

  Future<void> _logout() async {
    await ref.read(authProvider.notifier).logout();
  }

  String _typeLabel(String type) => switch (type) {
        'entrada' => 'Entrada',
        'saida_almoco' => 'Saída Almoço',
        'volta_almoco' => 'Volta Almoço',
        'saida' => 'Saída',
        _ => type,
      };

  Color _typeColor(String type) => switch (type) {
        'entrada' => AppColors.entrada,
        'saida_almoco' => AppColors.saidaAlmoco,
        'volta_almoco' => AppColors.voltaAlmoco,
        'saida' => AppColors.saida,
        _ => AppColors.primary,
      };

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(
        fit: StackFit.expand,
        children: [
          // ── Câmera de fundo ──────────────────────────────────────────────
          if (_camReady && _cam != null)
            Opacity(
              opacity: _step == _TotemStep.idle ? 1.0 : 0.3,
              child: CameraPreview(_cam!),
            )
          else
            Container(color: const Color(0xFF0F172A)),

          // ── Overlay escuro quando não está idle ──────────────────────────
          if (_step != _TotemStep.idle)
            Container(color: Colors.black.withValues(alpha: 0.65)),

          // ── Header: relógio + nome da empresa ────────────────────────────
          Positioned(
            top: 0,
            left: 0,
            right: 0,
            child: SafeArea(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Row(
                      children: [
                        const Icon(Icons.monitor, color: Colors.white70, size: 18),
                        const SizedBox(width: 8),
                        Text(
                          ref.watch(authProvider).user?.name ?? 'Totem',
                          style: const TextStyle(
                            color: Colors.white70,
                            fontSize: 13,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ],
                    ),
                    Text(
                      _clock,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 22,
                        fontWeight: FontWeight.bold,
                        fontFeatures: [FontFeature.tabularFigures()],
                      ),
                    ),
                    GestureDetector(
                      onLongPress: _logout,
                      child: const Icon(Icons.power_settings_new,
                          color: Colors.white30, size: 22),
                    ),
                  ],
                ),
              ),
            ),
          ),

          // ── Conteúdo central ─────────────────────────────────────────────
          Center(
            child: AnimatedSwitcher(
              duration: const Duration(milliseconds: 300),
              child: _buildCenterContent(),
            ),
          ),

          // ── Footer: instrução ou botão de scan ───────────────────────────
          if (_step == _TotemStep.idle)
            Positioned(
              bottom: 40,
              left: 0,
              right: 0,
              child: Column(
                children: [
                  // Oval guia facial
                  Container(
                    width: 180,
                    height: 230,
                    decoration: BoxDecoration(
                      border: Border.all(color: Colors.white60, width: 2),
                      borderRadius: BorderRadius.circular(110),
                    ),
                  ),
                  const SizedBox(height: 24),
                  const Text(
                    'Posicione seu rosto na câmera',
                    style: TextStyle(color: Colors.white70, fontSize: 14),
                  ),
                  const SizedBox(height: 16),
                  GestureDetector(
                    onTap: _scan,
                    child: Container(
                      width: 70,
                      height: 70,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: AppColors.primary,
                        border: Border.all(color: Colors.white30, width: 3),
                      ),
                      child: const Icon(Icons.face, color: Colors.white, size: 36),
                    ),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Toque para identificar',
                    style: TextStyle(color: Colors.white38, fontSize: 12),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildCenterContent() {
    switch (_step) {
      case _TotemStep.idle:
        return const SizedBox.shrink();

      case _TotemStep.scanning:
        return const _TotemCard(
          key: ValueKey('scanning'),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              CircularProgressIndicator(color: AppColors.primary, strokeWidth: 3),
              SizedBox(height: 16),
              Text(
                'Identificando...',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w600,
                  color: Colors.white,
                ),
              ),
            ],
          ),
        );

      case _TotemStep.identified:
        final r = _identified!;
        if (r.nextTypes.isEmpty || r.isComplete) {
          return _TotemCard(
            key: const ValueKey('complete'),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(Icons.check_circle, color: AppColors.success, size: 56),
                const SizedBox(height: 12),
                Text(
                  'Olá, ${r.firstName}!',
                  style: const TextStyle(
                      fontSize: 22, fontWeight: FontWeight.bold, color: Colors.white),
                ),
                const SizedBox(height: 6),
                const Text(
                  'Sua jornada de hoje está completa.',
                  style: TextStyle(color: Colors.white70, fontSize: 14),
                ),
              ],
            ),
          );
        }
        return _TotemCard(
          key: const ValueKey('identified'),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.person_outline, color: Colors.white70, size: 44),
              const SizedBox(height: 10),
              Text(
                'Olá, ${r.firstName}!',
                style: const TextStyle(
                    fontSize: 22, fontWeight: FontWeight.bold, color: Colors.white),
              ),
              const SizedBox(height: 4),
              Text(
                r.employeeCargo ?? '',
                style: const TextStyle(color: Colors.white60, fontSize: 13),
              ),
              const SizedBox(height: 20),
              const Text(
                'Registrar ponto:',
                style: TextStyle(color: Colors.white70, fontSize: 13),
              ),
              const SizedBox(height: 10),
              ...r.nextTypes.map((type) => Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: SizedBox(
                      width: 260,
                      child: ElevatedButton(
                        onPressed: () => _registerPoint(type),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: _typeColor(type),
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(vertical: 14),
                          shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(14)),
                        ),
                        child: Text(
                          _typeLabel(type),
                          style: const TextStyle(
                              fontSize: 16, fontWeight: FontWeight.bold),
                        ),
                      ),
                    ),
                  )),
              const SizedBox(height: 4),
              TextButton(
                onPressed: () => setState(() => _step = _TotemStep.idle),
                child: const Text('Cancelar',
                    style: TextStyle(color: Colors.white38, fontSize: 13)),
              ),
            ],
          ),
        );

      case _TotemStep.registering:
        return const _TotemCard(
          key: ValueKey('registering'),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              CircularProgressIndicator(color: AppColors.success, strokeWidth: 3),
              SizedBox(height: 16),
              Text(
                'Registrando ponto...',
                style: TextStyle(fontSize: 16, color: Colors.white),
              ),
            ],
          ),
        );

      case _TotemStep.success:
        final p = _lastPoint!;
        return _TotemCard(
          key: const ValueKey('success'),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 64,
                height: 64,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: _typeColor(p.type).withValues(alpha: 0.2),
                ),
                child: Icon(Icons.check_circle_outline,
                    color: _typeColor(p.type), size: 44),
              ),
              const SizedBox(height: 14),
              Text(
                p.employeeName,
                style: const TextStyle(
                    fontSize: 20, fontWeight: FontWeight.bold, color: Colors.white),
              ),
              const SizedBox(height: 6),
              Text(
                p.typeLabel,
                style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                    color: _typeColor(p.type)),
              ),
              const SizedBox(height: 4),
              Text(
                DateFormat('HH:mm').format(DateTime.parse(p.datetime)),
                style: const TextStyle(
                    fontSize: 28,
                    fontWeight: FontWeight.bold,
                    color: Colors.white),
              ),
              const SizedBox(height: 8),
              const Text(
                'Ponto registrado com sucesso!',
                style: TextStyle(color: Colors.white60, fontSize: 13),
              ),
            ],
          ),
        );

      case _TotemStep.failed:
        return _TotemCard(
          key: const ValueKey('failed'),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.face_retouching_off,
                  color: AppColors.error, size: 56),
              const SizedBox(height: 14),
              Text(
                _errorMsg ?? 'Rosto não reconhecido.',
                textAlign: TextAlign.center,
                style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                    color: Colors.white),
              ),
              const SizedBox(height: 8),
              const Text(
                'Tente novamente.',
                style: TextStyle(color: Colors.white54, fontSize: 13),
              ),
            ],
          ),
        );
    }
  }
}

/// Card centralizado com fundo semi-transparente.
class _TotemCard extends StatelessWidget {
  final Widget child;

  const _TotemCard({super.key, required this.child});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 32),
      padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 36),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: Colors.white.withValues(alpha: 0.15)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.3),
            blurRadius: 24,
            spreadRadius: 4,
          ),
        ],
      ),
      child: child,
    );
  }
}
