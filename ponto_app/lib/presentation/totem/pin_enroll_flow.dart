import 'dart:io';
import 'package:camera/camera.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/safe_camera_dispose.dart';
import '../../services/totem_service.dart';

/// Fluxo completo de enroll facial via PIN no totem.
///
/// Deve ser aberto como um overlay/dialog com [showDialog] ou [showModalBottomSheet].
/// Usa o [CameraController] passado pelo TotemScreen para evitar custo duplo.
class PinEnrollFlow extends ConsumerStatefulWidget {
  final CameraController? camera;

  const PinEnrollFlow({super.key, required this.camera});

  @override
  ConsumerState<PinEnrollFlow> createState() => _PinEnrollFlowState();
}

enum _Step { pin, confirm, capturing, uploading, success, error }

class _PinEnrollFlowState extends ConsumerState<PinEnrollFlow> {
  _Step _step = _Step.pin;

  // ── PIN ────────────────────────────────────────────────────────────────────
  final List<TextEditingController> _pinCtrls =
      List.generate(6, (_) => TextEditingController());
  final List<FocusNode> _pinFocuses = List.generate(6, (_) => FocusNode());
  bool _validatingPin = false;
  String? _pinError;

  // ── Resultado da validação ─────────────────────────────────────────────────
  String? _employeeName;
  String? _employeeCargo;
  String _pinAction = 'enroll';
  String _validatedPin = '';

  // ── Câmera local (só usada se a câmera externa não estiver disponível) ──────
  CameraController? _ownCamera;
  bool _ownCamReady = false;

  // ── Upload ─────────────────────────────────────────────────────────────────
  String? _errorMessage;

  CameraController? get _cam => widget.camera ?? _ownCamera;
  bool get _camReady =>
      widget.camera?.value.isInitialized == true || _ownCamReady;

  @override
  void initState() {
    super.initState();
    _pinFocuses.first.requestFocus();
    if (widget.camera == null || !widget.camera!.value.isInitialized) {
      _initOwnCamera();
    }
  }

  Future<void> _initOwnCamera() async {
    try {
      final cameras = await availableCameras();
      if (cameras.isEmpty) return;
      final front = cameras.firstWhere(
        (c) => c.lensDirection == CameraLensDirection.front,
        orElse: () => cameras.first,
      );
      _ownCamera = CameraController(front, ResolutionPreset.medium,
          enableAudio: false, imageFormatGroup: ImageFormatGroup.jpeg);
      await _ownCamera!.initialize();
      if (mounted) setState(() => _ownCamReady = true);
    } catch (_) {}
  }

  @override
  void dispose() {
    for (final c in _pinCtrls) { c.dispose(); }
    for (final f in _pinFocuses) { f.dispose(); }
    final c = _ownCamera;
    _ownCamera = null;
    scheduleDisposeCamera(c);
    super.dispose();
  }

  // ── Leitura do PIN ─────────────────────────────────────────────────────────
  String get _currentPin => _pinCtrls.map((c) => c.text).join();

  void _onDigitChanged(int index, String value) {
    if (value.length == 1 && index < 5) {
      _pinFocuses[index + 1].requestFocus();
    }
    if (value.isEmpty && index > 0) {
      _pinFocuses[index - 1].requestFocus();
    }
    if (_currentPin.length == 6) {
      _validatePin();
    }
  }

  Future<void> _validatePin() async {
    final pin = _currentPin;
    if (pin.length < 6) {
      setState(() => _pinError = 'Digite todos os 6 dígitos.');
      return;
    }
    setState(() {
      _validatingPin = true;
      _pinError = null;
    });

    final result =
        await ref.read(totemServiceProvider).validatePin(pin);

    if (!mounted) return;

    if (result.valid) {
      setState(() {
        _validatedPin  = pin;
        _employeeName  = result.name;
        _employeeCargo = result.cargo;
        _pinAction     = result.action;
        _step          = _Step.confirm;
        _validatingPin = false;
      });
    } else {
      // Limpar os campos e mostrar erro
      for (final c in _pinCtrls) { c.clear(); }
      _pinFocuses.first.requestFocus();
      setState(() {
        _pinError      = result.message ?? 'PIN inválido ou expirado.';
        _validatingPin = false;
      });
    }
  }

  // ── Captura e upload ───────────────────────────────────────────────────────
  Future<void> _capture() async {
    if (_cam == null || !_camReady) return;
    setState(() => _step = _Step.capturing);

    try {
      final xf   = await _cam!.takePicture();
      final file = File(xf.path);
      setState(() => _step = _Step.uploading);

      await ref.read(totemServiceProvider).enrollFace(_validatedPin, file);

      if (!mounted) return;
      setState(() => _step = _Step.success);
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _errorMessage = e.toString().replaceFirst('Exception: ', '');
        _step         = _Step.error;
      });
    }
  }

  // ── UI ─────────────────────────────────────────────────────────────────────
  @override
  Widget build(BuildContext context) {
    return Dialog.fullscreen(
      backgroundColor: const Color(0xFF0A0F1E),
      child: SafeArea(
        child: AnimatedSwitcher(
          duration: const Duration(milliseconds: 300),
          child: _buildStep(),
        ),
      ),
    );
  }

  Widget _buildStep() {
    return switch (_step) {
      _Step.pin        => _buildPinStep(),
      _Step.confirm    => _buildConfirmStep(),
      _Step.capturing  => _buildCapturingStep(),
      _Step.uploading  => _buildUploadingStep(),
      _Step.success    => _buildSuccessStep(),
      _Step.error      => _buildErrorStep(),
    };
  }

  // ── Ecrã 1: entrada do PIN ─────────────────────────────────────────────────
  Widget _buildPinStep() {
    return Column(
      key: const ValueKey('pin'),
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        const Icon(Icons.lock_open_outlined, color: Colors.white54, size: 40),
        const SizedBox(height: 16),
        const Text(
          'Cadastro Facial',
          style: TextStyle(
              color: Colors.white,
              fontSize: 22,
              fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 6),
        const Text(
          'Digite o PIN de 6 dígitos gerado pelo gestor',
          textAlign: TextAlign.center,
          style: TextStyle(color: Colors.white54, fontSize: 14),
        ),
        const SizedBox(height: 32),

        // Caixas de dígitos
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: List.generate(6, (i) {
            return Container(
              width: 44,
              height: 56,
              margin: const EdgeInsets.symmetric(horizontal: 4),
              child: TextFormField(
                controller: _pinCtrls[i],
                focusNode: _pinFocuses[i],
                keyboardType: TextInputType.number,
                textAlign: TextAlign.center,
                maxLength: 1,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                onChanged: (v) => _onDigitChanged(i, v),
                decoration: InputDecoration(
                  counterText: '',
                  filled: true,
                  fillColor: Colors.white.withValues(alpha: 0.08),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(10),
                    borderSide: BorderSide(
                        color: _pinError != null
                            ? AppColors.error
                            : Colors.white24),
                  ),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(10),
                    borderSide: BorderSide(
                        color: _pinError != null
                            ? AppColors.error
                            : Colors.white24),
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(10),
                    borderSide:
                        const BorderSide(color: AppColors.primary, width: 2),
                  ),
                ),
                style: const TextStyle(
                    color: Colors.white,
                    fontSize: 24,
                    fontWeight: FontWeight.bold),
              ),
            );
          }),
        ),

        if (_pinError != null) ...[
          const SizedBox(height: 12),
          Text(
            _pinError!,
            style: const TextStyle(color: AppColors.error, fontSize: 13),
            textAlign: TextAlign.center,
          ),
        ],

        const SizedBox(height: 28),

        if (_validatingPin)
          const CircularProgressIndicator(color: AppColors.primary)
        else
          SizedBox(
            width: 200,
            child: ElevatedButton(
              onPressed: _currentPin.length == 6 ? _validatePin : null,
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primary,
                minimumSize: const Size.fromHeight(48),
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12)),
              ),
              child: const Text('Confirmar PIN',
                  style: TextStyle(
                      color: Colors.white, fontWeight: FontWeight.bold)),
            ),
          ),

        const SizedBox(height: 16),
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Cancelar',
              style: TextStyle(color: Colors.white38, fontSize: 13)),
        ),
      ],
    );
  }

  // ── Ecrã 2: confirmar colaborador e tirar foto ─────────────────────────────
  Widget _buildConfirmStep() {
    final circleDiameter = MediaQuery.of(context).size.width * 0.65;
    final isUpdate = _pinAction == 'update';

    return Column(
      key: const ValueKey('confirm'),
      children: [
        const SizedBox(height: 24),
        // Info do colaborador
        Container(
          margin: const EdgeInsets.symmetric(horizontal: 24),
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.06),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: Colors.white12),
          ),
          child: Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.2),
                  shape: BoxShape.circle,
                ),
                child: Center(
                  child: Text(
                    (_employeeName?.isNotEmpty == true
                        ? _employeeName![0].toUpperCase()
                        : '?'),
                    style: const TextStyle(
                        color: AppColors.primary,
                        fontSize: 20,
                        fontWeight: FontWeight.bold),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      _employeeName ?? 'Colaborador',
                      style: const TextStyle(
                          color: Colors.white,
                          fontSize: 15,
                          fontWeight: FontWeight.w600),
                    ),
                    if ((_employeeCargo ?? '').isNotEmpty)
                      Text(
                        _employeeCargo!,
                        style: const TextStyle(
                            color: Colors.white54, fontSize: 12),
                      ),
                  ],
                ),
              ),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: isUpdate
                      ? AppColors.warning.withValues(alpha: 0.2)
                      : AppColors.success.withValues(alpha: 0.2),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  isUpdate ? 'Atualizar' : 'Novo cadastro',
                  style: TextStyle(
                    color: isUpdate ? AppColors.warning : AppColors.success,
                    fontSize: 11,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
        ),

        const SizedBox(height: 20),
        const Text(
          'Posicione o rosto no círculo',
          style: TextStyle(color: Colors.white70, fontSize: 14),
        ),
        const SizedBox(height: 12),

        // Preview da câmera
        SizedBox(
          width: circleDiameter,
          height: circleDiameter,
          child: ClipOval(
            child: _camReady && _cam != null
                ? _buildCameraPreview(circleDiameter)
                : Container(
                    color: const Color(0xFF1A2035),
                    child: const Icon(Icons.camera_alt,
                        color: Colors.white24, size: 40),
                  ),
          ),
        ),

        const Spacer(),

        // Botão capturar
        GestureDetector(
          onTap: _capture,
          child: Container(
            width: 72,
            height: 72,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: AppColors.primary,
              boxShadow: [
                BoxShadow(
                    color: AppColors.primary.withValues(alpha: 0.45),
                    blurRadius: 20,
                    spreadRadius: 2),
              ],
            ),
            child: const Icon(Icons.camera_alt, color: Colors.white, size: 32),
          ),
        ),
        const SizedBox(height: 8),
        const Text('Toque para tirar foto',
            style: TextStyle(color: Colors.white38, fontSize: 13)),
        const SizedBox(height: 16),
        TextButton(
          onPressed: () => setState(() => _step = _Step.pin),
          child: const Text('Voltar',
              style: TextStyle(color: Colors.white38, fontSize: 13)),
        ),
        const SizedBox(height: 24),
      ],
    );
  }

  Widget _buildCameraPreview(double diameter) {
    final cam = _cam!;
    final aspect = 1.0 / cam.value.aspectRatio;
    return AspectRatio(
      aspectRatio: 1,
      child: FittedBox(
        fit: BoxFit.cover,
        clipBehavior: Clip.hardEdge,
        child: SizedBox(
          width: cam.value.previewSize?.height ?? 480,
          height: cam.value.previewSize?.width ?? 640,
          child: AspectRatio(
              aspectRatio: aspect, child: CameraPreview(cam)),
        ),
      ),
    );
  }

  // ── Ecrã 3: capturando ─────────────────────────────────────────────────────
  Widget _buildCapturingStep() => _centered(
        key: const ValueKey('capturing'),
        icon: Icons.camera_alt,
        iconColor: AppColors.primary,
        text: 'Capturando...',
        spinner: true,
      );

  // ── Ecrã 4: enviando ───────────────────────────────────────────────────────
  Widget _buildUploadingStep() => _centered(
        key: const ValueKey('uploading'),
        icon: Icons.upload,
        iconColor: AppColors.primary,
        text: 'Cadastrando rosto...',
        spinner: true,
      );

  // ── Ecrã 5: sucesso ────────────────────────────────────────────────────────
  Widget _buildSuccessStep() {
    return _centered(
      key: const ValueKey('success'),
      icon: Icons.check_circle,
      iconColor: AppColors.success,
      text: 'Rosto cadastrado!',
      subtext: _employeeName ?? '',
      action: TextButton(
        onPressed: () => Navigator.of(context).pop(),
        child: const Text('Fechar',
            style: TextStyle(
                color: Colors.white,
                fontSize: 14,
                fontWeight: FontWeight.w600)),
      ),
    );
  }

  // ── Ecrã 6: erro ───────────────────────────────────────────────────────────
  Widget _buildErrorStep() {
    return _centered(
      key: const ValueKey('error'),
      icon: Icons.error_outline,
      iconColor: AppColors.error,
      text: 'Erro ao cadastrar',
      subtext: _errorMessage ?? 'Tente novamente.',
      action: Column(
        children: [
          ElevatedButton(
            onPressed: () => setState(() => _step = _Step.confirm),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.primary,
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12)),
            ),
            child: const Text('Tentar novamente',
                style: TextStyle(color: Colors.white)),
          ),
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Cancelar',
                style: TextStyle(color: Colors.white38)),
          ),
        ],
      ),
    );
  }

  Widget _centered({
    required Key key,
    required IconData icon,
    required Color iconColor,
    required String text,
    String subtext = '',
    bool spinner = false,
    Widget? action,
  }) {
    return Center(
      key: key,
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          if (spinner)
            SizedBox(
              width: 48,
              height: 48,
              child:
                  CircularProgressIndicator(color: iconColor, strokeWidth: 3),
            )
          else
            Icon(icon, color: iconColor, size: 56),
          const SizedBox(height: 16),
          Text(text,
              textAlign: TextAlign.center,
              style: const TextStyle(
                  color: Colors.white,
                  fontSize: 20,
                  fontWeight: FontWeight.bold)),
          if (subtext.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(subtext,
                style: const TextStyle(color: Colors.white54, fontSize: 14)),
          ],
          if (action != null) ...[
            const SizedBox(height: 24),
            action,
          ],
        ],
      ),
    );
  }
}
