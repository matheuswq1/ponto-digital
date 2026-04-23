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
    _newDateTime = widget.record.datetime;
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
      lastDate: DateTime.now().add(const Duration(days: 365)),
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
        const SnackBar(content: Text('Solicitação enviada. Aguarde a aprovação do gestor.')),
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Solicitar correção')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              'Ponto: ${widget.record.typeLabel} · ${widget.record.datetimeLocal}',
              style: const TextStyle(
                fontWeight: FontWeight.w600,
                color: AppColors.textPrimary,
              ),
            ),
            const SizedBox(height: 8),
            const Text(
              'Informe o horário correto e uma justificativa (mín. 20 caracteres).',
              style: TextStyle(color: AppColors.textSecondary, fontSize: 13),
            ),
            const SizedBox(height: 20),
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('Novo data e horário'),
              subtitle: Text(
                DateFormat("dd/MM/yyyy HH:mm", 'pt_BR').format(_newDateTime),
              ),
              trailing: const Icon(Icons.edit_calendar, color: AppColors.primary),
              onTap: _pickDateTime,
            ),
            const SizedBox(height: 8),
            const Align(
              alignment: Alignment.centerLeft,
              child: Text(
                'Tipo do ponto (se alterou)',
                style: TextStyle(fontSize: 12, color: AppColors.textHint),
              ),
            ),
            const SizedBox(height: 4),
            DropdownButton<String>(
              isExpanded: true,
              value: _newType,
              items: AppConstants.pointTypeLabels.entries
                  .map(
                    (e) => DropdownMenuItem(value: e.key, child: Text(e.value)),
                  )
                  .toList(),
              onChanged: (v) => setState(() => _newType = v),
            ),
            const SizedBox(height: 20),
            TextFormField(
              controller: _justification,
              minLines: 4,
              maxLines: 8,
              decoration: const InputDecoration(
                labelText: 'Justificativa *',
                hintText: 'Descreva o motivo da correção com detalhes...',
                border: OutlineInputBorder(),
                alignLabelWithHint: true,
              ),
            ),
            if (_error != null) ...[
              const SizedBox(height: 12),
              Text(
                _error!,
                style: const TextStyle(color: AppColors.error, fontSize: 13),
              ),
            ],
            const SizedBox(height: 24),
            FilledButton(
              onPressed: _sending ? null : _submit,
              child: _sending
                  ? const SizedBox(
                      height: 22,
                      width: 22,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : const Text('Enviar solicitação'),
            ),
          ],
        ),
      ),
    );
  }
}
