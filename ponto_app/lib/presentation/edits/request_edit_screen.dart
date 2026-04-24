import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../../data/datasources/time_record_datasource.dart';
import '../../data/models/time_record_model.dart';
import '../../core/errors/app_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/constants/app_constants.dart';
import '../home/today_provider.dart';
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

  @override
  void initState() {
    super.initState();
    // Usa o datetime já convertido para local (evita exibir UTC)
    _newDateTime = widget.record.datetime.toLocal();
    _newType = widget.record.type;
  }

  @override
  void dispose() {
    _justification.dispose();
    super.dispose();
  }

  Future<void> _pickDateTime() async {
    final d = await showDatePicker(
      context: context,
      initialDate: _newDateTime,
      firstDate: DateTime(2020),
      lastDate: DateTime.now(),
      locale: const Locale('pt', 'BR'),
    );
    if (d == null || !mounted) return;
    final t = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(_newDateTime),
    );
    if (t == null || !mounted) return;
    setState(() {
      _newDateTime = DateTime(d.year, d.month, d.day, t.hour, t.minute);
    });
  }

  Future<void> _submit() async {
    final id = widget.record.id;
    if (id == null) {
      setState(() => _error = 'Registro sem ID. Sincronize e tente novamente.');
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

  @override
  Widget build(BuildContext context) {
    final origTime = DateFormat('dd/MM/yyyy HH:mm', 'pt_BR')
        .format(widget.record.datetime.toLocal());
    final newTime =
        DateFormat('dd/MM/yyyy HH:mm', 'pt_BR').format(_newDateTime);
    final changed = _newDateTime != widget.record.datetime.toLocal() ||
        _newType != widget.record.type;

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Solicitar correção'),
        centerTitle: true,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // ── Card do ponto original ──────────────────────────────────
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: AppColors.surface,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: AppColors.divider),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Ponto original',
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                      color: AppColors.textHint,
                      letterSpacing: 0.5,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 10, vertical: 5),
                        decoration: BoxDecoration(
                          color: _typeColor(widget.record.type)
                              .withValues(alpha: 0.12),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Text(
                          widget.record.typeLabel,
                          style: TextStyle(
                            color: _typeColor(widget.record.type),
                            fontWeight: FontWeight.bold,
                            fontSize: 13,
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Text(
                        origTime,
                        style: const TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w600,
                          color: AppColors.textPrimary,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),

            const SizedBox(height: 16),

            // ── Seção: nova data/hora ───────────────────────────────────
            _SectionLabel(label: 'Novo horário'),
            const SizedBox(height: 8),
            InkWell(
              onTap: _pickDateTime,
              borderRadius: BorderRadius.circular(12),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                decoration: BoxDecoration(
                  color: AppColors.surface,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(
                    color: _newDateTime != widget.record.datetime.toLocal()
                        ? AppColors.primary
                        : AppColors.divider,
                    width: _newDateTime != widget.record.datetime.toLocal() ? 2 : 1,
                  ),
                ),
                child: Row(
                  children: [
                    Icon(
                      Icons.edit_calendar,
                      color: _newDateTime != widget.record.datetime.toLocal()
                          ? AppColors.primary
                          : AppColors.textHint,
                      size: 20,
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        newTime,
                        style: TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w600,
                          color: _newDateTime != widget.record.datetime.toLocal()
                              ? AppColors.primary
                              : AppColors.textPrimary,
                        ),
                      ),
                    ),
                    const Icon(Icons.chevron_right,
                        color: AppColors.textHint, size: 20),
                  ],
                ),
              ),
            ),

            const SizedBox(height: 16),

            // ── Seção: tipo do ponto ────────────────────────────────────
            _SectionLabel(label: 'Tipo do ponto'),
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
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        decoration: BoxDecoration(
                          color:
                              selected ? color.withValues(alpha: 0.12) : AppColors.surface,
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
                              e.key == 'entrada' ? Icons.login : Icons.logout,
                              color: selected ? color : AppColors.textHint,
                              size: 18,
                            ),
                            const SizedBox(width: 6),
                            Text(
                              e.value,
                              style: TextStyle(
                                color: selected ? color : AppColors.textSecondary,
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
            if (changed) ...[
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.06),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(
                      color: AppColors.primary.withValues(alpha: 0.2)),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.swap_horiz,
                        color: AppColors.primary, size: 18),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        '${widget.record.typeLabel} $origTime  →  ${AppConstants.pointTypeLabels[_newType] ?? _newType} $newTime',
                        style: const TextStyle(
                          color: AppColors.primary,
                          fontSize: 12,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
            ],

            // ── Justificativa ───────────────────────────────────────────
            _SectionLabel(label: 'Justificativa'),
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
            // Contador e requisito mínimo
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Text(
                _justification.text.length < 20
                    ? 'Mínimo 20 caracteres (${_justification.text.length}/20)'
                    : '${_justification.text.length} caracteres',
                style: TextStyle(
                  fontSize: 11,
                  color: _justification.text.length < 20
                      ? AppColors.textHint
                      : AppColors.success,
                ),
              ),
            ),

            // ── Erro ────────────────────────────────────────────────────
            if (_error != null) ...[
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppColors.error.withValues(alpha: 0.07),
                  borderRadius: BorderRadius.circular(10),
                  border:
                      Border.all(color: AppColors.error.withValues(alpha: 0.25)),
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

            // ── Botão enviar ────────────────────────────────────────────
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

            // ── Aviso ────────────────────────────────────────────────────
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
