import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../../data/datasources/time_record_datasource.dart';
import '../../data/models/time_record_model.dart';
import '../../core/errors/app_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/constants/app_constants.dart';
import '../home/today_provider.dart';
import '../history/history_provider.dart';
import 'edit_requests_provider.dart';

class RequestEditScreen extends ConsumerStatefulWidget {
  final TimeRecordModel record;

  const RequestEditScreen({super.key, required this.record});

  @override
  ConsumerState<RequestEditScreen> createState() => _RequestEditScreenState();
}

class _RequestEditScreenState extends ConsumerState<RequestEditScreen> {
  late DateTime _newDateTime;
  String? _newType;
  final _justification = TextEditingController();
  bool _sending = false;
  String? _error;

  // Controladores e focus nodes para hora e minuto
  late TextEditingController _hourCtrl;
  late TextEditingController _minuteCtrl;
  late FocusNode _hourFocus;
  late FocusNode _minuteFocus;

  @override
  void initState() {
    super.initState();
    _newDateTime = widget.record.datetime;
    _newType = widget.record.type;
    _hourCtrl = TextEditingController(
        text: _newDateTime.hour.toString().padLeft(2, '0'));
    _minuteCtrl = TextEditingController(
        text: _newDateTime.minute.toString().padLeft(2, '0'));

    // Formatar e aplicar só quando o campo perde o foco
    _hourFocus = FocusNode()
      ..addListener(() {
        if (!_hourFocus.hasFocus) _applyTimeFields();
      });
    _minuteFocus = FocusNode()
      ..addListener(() {
        if (!_minuteFocus.hasFocus) _applyTimeFields();
      });
  }

  @override
  void dispose() {
    _justification.dispose();
    _hourCtrl.dispose();
    _minuteCtrl.dispose();
    _hourFocus.dispose();
    _minuteFocus.dispose();
    super.dispose();
  }

  // ── Selecionar data ────────────────────────────────────────────────────────
  Future<void> _pickDate() async {
    final d = await showDatePicker(
      context: context,
      initialDate: _newDateTime,
      firstDate: DateTime(2020),
      lastDate: DateTime.now(),
    );
    if (d == null || !mounted) return;
    setState(() {
      _newDateTime = DateTime(
        d.year, d.month, d.day,
        _newDateTime.hour, _newDateTime.minute,
      );
    });
  }

  // ── Selecionar hora via TimePicker nativo ──────────────────────────────────
  Future<void> _pickTime() async {
    final t = await showTimePicker(
      context: context,
      initialTime: TimeOfDay(hour: _newDateTime.hour, minute: _newDateTime.minute),
      builder: (context, child) => MediaQuery(
        data: MediaQuery.of(context).copyWith(alwaysUse24HourFormat: true),
        child: child!,
      ),
    );
    if (t == null || !mounted) return;
    setState(() {
      _newDateTime = DateTime(
        _newDateTime.year, _newDateTime.month, _newDateTime.day,
        t.hour, t.minute,
      );
      _hourCtrl.text = t.hour.toString().padLeft(2, '0');
      _minuteCtrl.text = t.minute.toString().padLeft(2, '0');
    });
  }

  // ── Aplicar hora/minuto dos campos de texto (só ao perder foco ou enviar) ──
  void _applyTimeFields() {
    final raw = _hourCtrl.text.trim();
    final rawM = _minuteCtrl.text.trim();
    final h = int.tryParse(raw) ?? _newDateTime.hour;
    final m = int.tryParse(rawM) ?? _newDateTime.minute;
    final hClamped = h.clamp(0, 23);
    final mClamped = m.clamp(0, 59);

    final fmtH = hClamped.toString().padLeft(2, '0');
    final fmtM = mClamped.toString().padLeft(2, '0');

    // Só atualizar se realmente mudou para não interferir com a edição em curso
    final dateChanged = hClamped != _newDateTime.hour || mClamped != _newDateTime.minute;
    final textHChanged = _hourCtrl.text != fmtH;
    final textMChanged = _minuteCtrl.text != fmtM;

    if (dateChanged || textHChanged || textMChanged) {
      setState(() {
        _newDateTime = DateTime(
          _newDateTime.year, _newDateTime.month, _newDateTime.day,
          hClamped, mClamped,
        );
      });
      // Atualizar texto preservando posição do cursor apenas se diferente
      if (textHChanged) {
        _hourCtrl.value = TextEditingValue(
          text: fmtH,
          selection: TextSelection.collapsed(offset: fmtH.length),
        );
      }
      if (textMChanged) {
        _minuteCtrl.value = TextEditingValue(
          text: fmtM,
          selection: TextSelection.collapsed(offset: fmtM.length),
        );
      }
    }
  }

  // ── Validações de horário ─────────────────────────────────────────────────
  String? _validateDateTime(DateTime proposed) {
    // 1. Não pode ser no futuro
    if (proposed.isAfter(DateTime.now())) {
      return 'O horário não pode ser no futuro.';
    }

    // 2. Buscar registos do mesmo dia no histórico
    final sameDay = DateFormat('yyyy-MM-dd').format(widget.record.datetime);
    final allRecords = ref.read(historyProvider).records
        .where((r) => DateFormat('yyyy-MM-dd').format(r.datetime) == sameDay)
        .where((r) => r.id != widget.record.id) // excluir o próprio registo
        .toList()
      ..sort((a, b) => a.datetime.compareTo(b.datetime));

    // Posição do registo a ser corrigido (ordenado por data original)
    final allWithCurrent = [
      ...allRecords,
      TimeRecordModel(
        id: widget.record.id,
        employeeId: widget.record.employeeId,
        type: _newType ?? widget.record.type,
        typeLabel: widget.record.typeLabel,
        datetime: proposed,
        status: widget.record.status,
      ),
    ]..sort((a, b) => a.datetime.compareTo(b.datetime));

    final idx = allWithCurrent.indexWhere((r) => r.id == widget.record.id);

    // 3. Não pode ser anterior ao registo imediatamente anterior
    if (idx > 0) {
      final prev = allWithCurrent[idx - 1];
      if (!proposed.isAfter(prev.datetime)) {
        final fmtPrev = DateFormat('HH:mm').format(prev.datetime);
        return 'O horário deve ser posterior ao registo anterior (${prev.typeLabel} às $fmtPrev).';
      }
    }

    // 4. Não pode ser posterior ao registo imediatamente seguinte
    if (idx < allWithCurrent.length - 1) {
      final next = allWithCurrent[idx + 1];
      if (!proposed.isBefore(next.datetime)) {
        final fmtNext = DateFormat('HH:mm').format(next.datetime);
        return 'O horário deve ser anterior ao registo seguinte (${next.typeLabel} às $fmtNext).';
      }
    }

    return null; // Válido
  }

  // ── Enviar ────────────────────────────────────────────────────────────────
  Future<void> _submit() async {
    _applyTimeFields();
    // Aguardar o próximo frame para _newDateTime reflectir o applyTimeFields
    await Future.microtask(() {});

    final id = widget.record.id;
    if (id == null) {
      setState(() => _error = 'Registro sem ID. Sincronize e tente novamente.');
      return;
    }

    // Validar horário
    final timeError = _validateDateTime(_newDateTime);
    if (timeError != null) {
      setState(() => _error = timeError);
      return;
    }

    final text = _justification.text.trim();
    if (text.length < 20) {
      setState(() => _error = 'A justificativa deve ter no mínimo 20 caracteres.');
      return;
    }
    setState(() {
      _sending = true;
      _error = null;
    });
    try {
      await ref.read(timeRecordDatasourceProvider).requestEdit(
            id,
            newDatetime: _newDateTime,
            newType: _newType != widget.record.type ? _newType : null,
            justification: text,
          );
      if (!mounted) return;
      ref.read(todayProvider.notifier).refresh();
      ref.invalidate(editRequestsProvider);
      context.pop();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Solicitação enviada. Aguarde a aprovação do gestor.'),
          backgroundColor: AppColors.success,
        ),
      );
    } on AppException catch (e) {
      setState(() {
        _sending = false;
        _error = e.firstError() ?? e.message;
      });
    } catch (e) {
      setState(() {
        _sending = false;
        _error = e.toString();
      });
    }
  }

  Color _typeColor(String type) => switch (type) {
        'entrada' => AppColors.entrada,
        'saida' => AppColors.saida,
        _ => AppColors.primary,
      };

  bool get _dateChanged =>
      _newDateTime.year != widget.record.datetime.year ||
        _newDateTime.month != widget.record.datetime.month ||
        _newDateTime.day != widget.record.datetime.day;

  bool get _timeChanged =>
      _newDateTime.hour != widget.record.datetime.hour ||
        _newDateTime.minute != widget.record.datetime.minute;

  bool get _typeChanged => _newType != widget.record.type;

  bool get _changed => _dateChanged || _timeChanged || _typeChanged;

  @override
  Widget build(BuildContext context) {
    final origLocal = widget.record.datetime;
    final fmtDate = DateFormat('dd/MM/yyyy', 'pt_BR');
    final fmtFull = DateFormat('dd/MM/yyyy HH:mm', 'pt_BR');

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Solicitar correção'),
        centerTitle: true,
      ),
      body: GestureDetector(
        onTap: () => FocusScope.of(context).unfocus(),
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [

              // ── Ponto original ──────────────────────────────────────────
              _OriginalCard(
                record: widget.record,
                typeColor: _typeColor(widget.record.type),
                fmtFull: fmtFull,
              ),

              const SizedBox(height: 20),

              // ── Selecionar DATA ─────────────────────────────────────────
              const _SectionLabel(label: 'Nova data'),
              const SizedBox(height: 8),
              Material(
                color: Colors.transparent,
                child: InkWell(
                  onTap: _pickDate,
                  borderRadius: BorderRadius.circular(14),
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
                    decoration: BoxDecoration(
                      color: AppColors.surface,
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(
                        color: _dateChanged ? AppColors.primary : AppColors.divider,
                        width: _dateChanged ? 2 : 1,
                      ),
                    ),
                    child: Row(
                      children: [
                        Container(
                          width: 42,
                          height: 42,
                          decoration: BoxDecoration(
                            color: (_dateChanged ? AppColors.primary : AppColors.textHint)
                                .withValues(alpha: 0.1),
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: Icon(
                            Icons.calendar_today_rounded,
                            color: _dateChanged ? AppColors.primary : AppColors.textHint,
                            size: 20,
                          ),
                        ),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                fmtDate.format(_newDateTime),
                                style: TextStyle(
                                  fontSize: 18,
                                  fontWeight: FontWeight.bold,
                                  color: _dateChanged
                                      ? AppColors.primary
                                      : AppColors.textPrimary,
                                ),
                              ),
                              Text(
                                DateFormat("EEEE", 'pt_BR').format(_newDateTime),
                                style: const TextStyle(
                                    fontSize: 12, color: AppColors.textSecondary),
                              ),
                            ],
                          ),
                        ),
                        const Column(
                          children: [
                            Icon(Icons.touch_app_rounded,
                                size: 14, color: AppColors.textHint),
                            SizedBox(height: 2),
                            Text('Toque para\nalternar',
                                textAlign: TextAlign.center,
                                style: TextStyle(
                                    fontSize: 10, color: AppColors.textHint)),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              ),

              const SizedBox(height: 16),

              // ── Selecionar HORA ─────────────────────────────────────────
              const _SectionLabel(label: 'Novo horário'),
              const SizedBox(height: 8),

              // Campos de texto de hora e minuto + botão relógio
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Campo hora
                  Expanded(
                    child: _TimeField(
                      controller: _hourCtrl,
                      focusNode: _hourFocus,
                      nextFocus: _minuteFocus,
                      label: 'Hora',
                      hint: '00–23',
                      max: 23,
                      changed: _timeChanged,
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 10),
                    child: Column(
                      children: [
                        const SizedBox(height: 18),
                        Text(
                          ':',
                          style: TextStyle(
                            fontSize: 32,
                            fontWeight: FontWeight.bold,
                            color: _timeChanged
                                ? AppColors.primary
                                : AppColors.textSecondary,
                          ),
                        ),
                      ],
                    ),
                  ),
                  // Campo minuto
                  Expanded(
                    child: _TimeField(
                      controller: _minuteCtrl,
                      focusNode: _minuteFocus,
                      label: 'Minuto',
                      hint: '00–59',
                      max: 59,
                      changed: _timeChanged,
                    ),
                  ),
                  const SizedBox(width: 10),
                  // Botão relógio nativo (TimePicker)
                  Column(
                    children: [
                      const SizedBox(height: 4),
                      const Text('Relógio',
                          style: TextStyle(
                              fontSize: 11, color: AppColors.textHint)),
                      const SizedBox(height: 4),
                      Material(
                        color: Colors.transparent,
                        child: InkWell(
                          onTap: _pickTime,
                          borderRadius: BorderRadius.circular(12),
                          child: Container(
                            width: 56,
                            height: 56,
                            decoration: BoxDecoration(
                              color: (_timeChanged ? AppColors.primary : AppColors.textHint)
                                  .withValues(alpha: 0.1),
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(
                                color: _timeChanged
                                    ? AppColors.primary.withValues(alpha: 0.5)
                                    : AppColors.divider,
                              ),
                            ),
                            child: Icon(
                              Icons.access_time_rounded,
                              color: _timeChanged ? AppColors.primary : AppColors.textHint,
                              size: 26,
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),

              // Preview hora actual seleccionada
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Center(
                  child: Text(
                    'Horário seleccionado: ${_newDateTime.hour.toString().padLeft(2, '0')}:${_newDateTime.minute.toString().padLeft(2, '0')}',
                    style: TextStyle(
                      fontSize: 13,
                      color:
                          _timeChanged ? AppColors.primary : AppColors.textSecondary,
                      fontWeight: _timeChanged ? FontWeight.w600 : FontWeight.normal,
                    ),
                  ),
                ),
              ),

              const SizedBox(height: 16),

              // ── Tipo do ponto ───────────────────────────────────────────
              const _SectionLabel(label: 'Tipo do ponto'),
              const SizedBox(height: 8),
              Row(
                children: AppConstants.pointTypeLabels.entries.map((e) {
                  final selected = _newType == e.key;
                  final color = _typeColor(e.key);
                  return Expanded(
                    child: Padding(
                      padding: EdgeInsets.only(
                          right: e.key != AppConstants.pointTypeLabels.keys.last
                              ? 10
                              : 0),
                      child: GestureDetector(
                        onTap: () => setState(() => _newType = e.key),
                        child: AnimatedContainer(
                          duration: const Duration(milliseconds: 200),
                          padding: const EdgeInsets.symmetric(vertical: 16),
                          decoration: BoxDecoration(
                            color: selected
                                ? color.withValues(alpha: 0.12)
                                : AppColors.surface,
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(
                              color: selected ? color : AppColors.divider,
                              width: selected ? 2 : 1,
                            ),
                          ),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Icon(
                                e.key == 'entrada'
                                    ? Icons.login
                                    : Icons.logout,
                                color: selected ? color : AppColors.textHint,
                                size: 18,
                              ),
                              const SizedBox(width: 6),
                              Text(
                                e.value,
                                style: TextStyle(
                                  color: selected
                                      ? color
                                      : AppColors.textSecondary,
                                  fontWeight: selected
                                      ? FontWeight.bold
                                      : FontWeight.normal,
                                  fontSize: 14,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  );
                }).toList(),
              ),

              const SizedBox(height: 16),

              // ── Preview da alteração ────────────────────────────────────
              if (_changed) ...[
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: AppColors.primary.withValues(alpha: 0.06),
                    borderRadius: BorderRadius.circular(12),
                    border:
                        Border.all(color: AppColors.primary.withValues(alpha: 0.2)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Row(
                        children: [
                          Icon(Icons.swap_horiz,
                              color: AppColors.primary, size: 16),
                          SizedBox(width: 6),
                          Text('Alterações solicitadas',
                              style: TextStyle(
                                  color: AppColors.primary,
                                  fontWeight: FontWeight.bold,
                                  fontSize: 12)),
                        ],
                      ),
                      const SizedBox(height: 8),
                      if (_dateChanged)
                        _DiffRow(
                          icon: Icons.calendar_today,
                          label: 'Data',
                          before: fmtDate.format(origLocal),
                          after: fmtDate.format(_newDateTime),
                        ),
                      if (_timeChanged)
                        _DiffRow(
                          icon: Icons.access_time,
                          label: 'Hora',
                          before:
                              '${origLocal.hour.toString().padLeft(2, '0')}:${origLocal.minute.toString().padLeft(2, '0')}',
                          after:
                              '${_newDateTime.hour.toString().padLeft(2, '0')}:${_newDateTime.minute.toString().padLeft(2, '0')}',
                        ),
                      if (_typeChanged)
                        _DiffRow(
                          icon: Icons.swap_horiz,
                          label: 'Tipo',
                          before: widget.record.typeLabel,
                          after: AppConstants.pointTypeLabels[_newType] ?? _newType ?? '',
                        ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
              ],

              // ── Justificativa ───────────────────────────────────────────
              const _SectionLabel(label: 'Justificativa'),
              const SizedBox(height: 8),
              TextFormField(
                controller: _justification,
                minLines: 4,
                maxLines: 8,
                maxLength: 500,
                onChanged: (_) => setState(() {}),
                decoration: const InputDecoration(
                  hintText: 'Descreva o motivo da correção com detalhes...',
                  alignLabelWithHint: true,
                ),
              ),
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Text(
                  _justification.text.length < 20
                      ? 'Mínimo 20 caracteres (${_justification.text.length}/20)'
                      : '✓ ${_justification.text.length} caracteres',
                  style: TextStyle(
                    fontSize: 11,
                    color: _justification.text.length < 20
                        ? AppColors.textHint
                        : AppColors.success,
                  ),
                ),
              ),

              // ── Aviso em tempo real (validação de horário) ───────────────
              Builder(builder: (context) {
                if (!_changed) return const SizedBox.shrink();
                final warn = _validateDateTime(_newDateTime);
                if (warn == null) return const SizedBox.shrink();
                return Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: AppColors.warning.withValues(alpha: 0.08),
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: AppColors.warning.withValues(alpha: 0.4)),
                    ),
                    child: Row(
                      children: [
                        const Icon(Icons.warning_amber_rounded,
                            color: AppColors.warning, size: 18),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(warn,
                              style: const TextStyle(
                                  color: AppColors.warning, fontSize: 13)),
                        ),
                      ],
                    ),
                  ),
                );
              }),

              // ── Erro ─────────────────────────────────────────────────────
              if (_error != null) ...[
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppColors.error.withValues(alpha: 0.07),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(
                        color: AppColors.error.withValues(alpha: 0.25)),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.error_outline,
                          color: AppColors.error, size: 18),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(_error!,
                            style: const TextStyle(
                                color: AppColors.error, fontSize: 13)),
                      ),
                    ],
                  ),
                ),
              ],

              const SizedBox(height: 24),

              // ── Botão enviar ─────────────────────────────────────────────
              ElevatedButton(
                onPressed: _sending ? null : _submit,
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  minimumSize: const Size.fromHeight(52),
                  shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14)),
                ),
                child: _sending
                    ? const SizedBox(
                        height: 22,
                        width: 22,
                        child: CircularProgressIndicator(
                            strokeWidth: 2.5, color: Colors.white),
                      )
                    : const Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(Icons.send, size: 18, color: Colors.white),
                          SizedBox(width: 8),
                          Text('Enviar solicitação',
                              style: TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.bold)),
                        ],
                      ),
              ),

              const SizedBox(height: 12),
              const Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.info_outline, size: 13, color: AppColors.textHint),
                  SizedBox(width: 4),
                  Text(
                    'A correção será analisada pelo gestor.',
                    style: TextStyle(fontSize: 11, color: AppColors.textHint),
                  ),
                ],
              ),
              const SizedBox(height: 20),
            ],
          ),
        ),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Widgets auxiliares
// ─────────────────────────────────────────────────────────────────────────────

class _OriginalCard extends StatelessWidget {
  final TimeRecordModel record;
  final Color typeColor;
  final DateFormat fmtFull;

  const _OriginalCard(
      {required this.record, required this.typeColor, required this.fmtFull});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.divider),
      ),
      child: Row(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: typeColor.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(
              record.type == 'entrada' ? Icons.login : Icons.logout,
              color: typeColor,
              size: 22,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Ponto original',
                  style: const TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w600,
                      color: AppColors.textHint),
                ),
                const SizedBox(height: 4),
                Text(
                  '${record.typeLabel}  ·  ${fmtFull.format(record.datetime)}',
                  style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                      color: AppColors.textPrimary),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _TimeField extends StatelessWidget {
  final TextEditingController controller;
  final FocusNode focusNode;
  final FocusNode? nextFocus;
  final String label;
  final String hint;
  final int max;
  final bool changed;

  const _TimeField({
    required this.controller,
    required this.focusNode,
    this.nextFocus,
    required this.label,
    required this.hint,
    required this.max,
    required this.changed,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label,
            style: const TextStyle(fontSize: 11, color: AppColors.textHint)),
        const SizedBox(height: 4),
        TextFormField(
          controller: controller,
          focusNode: focusNode,
          keyboardType: TextInputType.number,
          textAlign: TextAlign.center,
          // Sem maxLength para não mostrar contador — a validação é por clamp
          inputFormatters: [FilteringTextInputFormatter.digitsOnly],
          // Selecionar tudo ao ganhar foco para facilitar substituição
          onTap: () => controller.selection = TextSelection(
            baseOffset: 0,
            extentOffset: controller.text.length,
          ),
          // Ao confirmar, mover para próximo campo (minuto) ou fechar teclado
          onEditingComplete: () {
            if (nextFocus != null) {
              FocusScope.of(context).requestFocus(nextFocus);
            } else {
              focusNode.unfocus();
            }
          },
          decoration: InputDecoration(
            counterText: '',
            hintText: hint,
            hintStyle:
                const TextStyle(fontSize: 12, color: AppColors.textHint),
            filled: true,
            fillColor: changed
                ? AppColors.primary.withValues(alpha: 0.06)
                : AppColors.surface,
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: BorderSide(
                  color: changed ? AppColors.primary : AppColors.divider),
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: BorderSide(
                  color: changed ? AppColors.primary : AppColors.divider,
                  width: changed ? 2 : 1),
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide:
                  const BorderSide(color: AppColors.primary, width: 2),
            ),
            contentPadding:
                const EdgeInsets.symmetric(vertical: 14, horizontal: 8),
          ),
          style: TextStyle(
            fontSize: 28,
            fontWeight: FontWeight.bold,
            color: changed ? AppColors.primary : AppColors.textPrimary,
          ),
        ),
      ],
    );
  }
}

class _DiffRow extends StatelessWidget {
  final IconData icon;
  final String label;
  final String before;
  final String after;

  const _DiffRow(
      {required this.icon,
      required this.label,
      required this.before,
      required this.after});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Row(
        children: [
          Icon(icon, size: 14, color: AppColors.primary),
          const SizedBox(width: 6),
          Text('$label: ',
              style: const TextStyle(
                  fontSize: 12, color: AppColors.textSecondary)),
          Text(before,
              style: const TextStyle(
                  fontSize: 12,
                  color: AppColors.textHint,
                  decoration: TextDecoration.lineThrough)),
          const SizedBox(width: 6),
          const Icon(Icons.arrow_forward, size: 12, color: AppColors.primary),
          const SizedBox(width: 6),
          Text(after,
              style: const TextStyle(
                  fontSize: 12,
                  color: AppColors.primary,
                  fontWeight: FontWeight.bold)),
        ],
      ),
    );
  }
}

class _SectionLabel extends StatelessWidget {
  final String label;
  const _SectionLabel({required this.label});

  @override
  Widget build(BuildContext context) {
    return Text(
      label.toUpperCase(),
      style: const TextStyle(
        fontSize: 11,
        fontWeight: FontWeight.w700,
        color: AppColors.textHint,
        letterSpacing: 0.8,
      ),
    );
  }
}
