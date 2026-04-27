import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../../core/theme/app_theme.dart';
import '../../data/datasources/time_record_datasource.dart';

class RequestAddPointScreen extends ConsumerStatefulWidget {
  /// Data sugerida (vinda do histórico do dia)
  final DateTime? suggestedDate;

  const RequestAddPointScreen({super.key, this.suggestedDate});

  @override
  ConsumerState<RequestAddPointScreen> createState() => _RequestAddPointScreenState();
}

class _RequestAddPointScreenState extends ConsumerState<RequestAddPointScreen> {
  final _formKey = GlobalKey<FormState>();
  final _justCtrl = TextEditingController();

  String _type = 'saida';
  late DateTime _selectedDate;
  late TimeOfDay _selectedTime;
  bool _loading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    final base = widget.suggestedDate ?? DateTime.now();
    _selectedDate = DateTime(base.year, base.month, base.day);
    _selectedTime = TimeOfDay.fromDateTime(base);
  }

  @override
  void dispose() {
    _justCtrl.dispose();
    super.dispose();
  }

  DateTime get _combinedDatetime => DateTime(
        _selectedDate.year,
        _selectedDate.month,
        _selectedDate.day,
        _selectedTime.hour,
        _selectedTime.minute,
      );

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _selectedDate,
      firstDate: DateTime.now().subtract(const Duration(days: 90)),
      lastDate: DateTime.now(),
      locale: const Locale('pt', 'BR'),
    );
    if (picked != null) setState(() => _selectedDate = picked);
  }

  Future<void> _pickTime() async {
    final picked = await showTimePicker(
      context: context,
      initialTime: _selectedTime,
      builder: (ctx, child) => MediaQuery(
        data: MediaQuery.of(ctx).copyWith(alwaysUse24HourFormat: true),
        child: child!,
      ),
    );
    if (picked != null) setState(() => _selectedTime = picked);
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_combinedDatetime.isAfter(DateTime.now())) {
      setState(() => _error = 'A data/hora não pode ser no futuro.');
      return;
    }
    setState(() { _loading = true; _error = null; });

    try {
      final ds = ref.read(timeRecordDatasourceProvider);
      await ds.requestAddition(
        type: _type,
        datetime: _combinedDatetime,
        justification: _justCtrl.text.trim(),
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
        content: Text('Solicitação enviada! Aguardando aprovação do gestor.'),
        backgroundColor: AppColors.success,
      ));
      context.pop();
    } catch (e) {
      setState(() => _error = e.toString().replaceAll('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final dateLabel = DateFormat('dd/MM/yyyy').format(_selectedDate);
    final timeLabel = _selectedTime.format(context);

    return Scaffold(
      appBar: AppBar(title: const Text('Solicitar adição de ponto')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Info
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: AppColors.primary.withValues(alpha: 0.07),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: AppColors.primary.withValues(alpha: 0.2)),
                  ),
                  child: const Row(
                    children: [
                      Icon(Icons.info_outline, color: AppColors.primary, size: 18),
                      SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          'Use esta opção quando esqueceu de bater um ponto. '
                          'Após enviado, o gestor irá analisar e aprovar ou rejeitar.',
                          style: TextStyle(fontSize: 13, color: AppColors.textSecondary),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 24),

                // Tipo
                const Text('Tipo do ponto', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                const SizedBox(height: 8),
                Row(
                  children: [
                    Expanded(
                      child: _TypeButton(
                        label: 'Entrada',
                        icon: Icons.login_rounded,
                        color: AppColors.entrada,
                        selected: _type == 'entrada',
                        onTap: () => setState(() => _type = 'entrada'),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _TypeButton(
                        label: 'Saída',
                        icon: Icons.logout_rounded,
                        color: AppColors.saida,
                        selected: _type == 'saida',
                        onTap: () => setState(() => _type = 'saida'),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 20),

                // Data e Hora
                const Text('Data e hora', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                const SizedBox(height: 8),
                Row(
                  children: [
                    Expanded(
                      child: InkWell(
                        onTap: _pickDate,
                        borderRadius: BorderRadius.circular(12),
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
                          decoration: BoxDecoration(
                            border: Border.all(color: const Color(0xFFE2E8F0)),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Row(
                            children: [
                              const Icon(Icons.calendar_today_outlined, size: 18, color: AppColors.primary),
                              const SizedBox(width: 8),
                              Text(dateLabel, style: const TextStyle(fontSize: 15)),
                            ],
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: InkWell(
                        onTap: _pickTime,
                        borderRadius: BorderRadius.circular(12),
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
                          decoration: BoxDecoration(
                            border: Border.all(color: const Color(0xFFE2E8F0)),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Row(
                            children: [
                              const Icon(Icons.access_time_rounded, size: 18, color: AppColors.primary),
                              const SizedBox(width: 8),
                              Text(timeLabel, style: const TextStyle(fontSize: 15)),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 20),

                // Justificativa
                const Text('Justificativa', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                const SizedBox(height: 8),
                TextFormField(
                  controller: _justCtrl,
                  maxLines: 4,
                  maxLength: 500,
                  decoration: InputDecoration(
                    hintText: 'Descreva o motivo pelo qual não bateu o ponto no horário correto...',
                    hintStyle: const TextStyle(fontSize: 13),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                    filled: true,
                    fillColor: const Color(0xFFF8FAFC),
                  ),
                  validator: (v) {
                    if (v == null || v.trim().length < 20) {
                      return 'Mínimo de 20 caracteres.';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: 8),

                if (_error != null)
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: AppColors.error.withValues(alpha: 0.08),
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: AppColors.error.withValues(alpha: 0.3)),
                    ),
                    child: Row(
                      children: [
                        const Icon(Icons.error_outline, color: AppColors.error, size: 16),
                        const SizedBox(width: 8),
                        Expanded(child: Text(_error!, style: const TextStyle(color: AppColors.error, fontSize: 13))),
                      ],
                    ),
                  ),

                const SizedBox(height: 24),

                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton.icon(
                    onPressed: _loading ? null : _submit,
                    icon: _loading
                        ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                        : const Icon(Icons.send_rounded, size: 18),
                    label: Text(_loading ? 'Enviando...' : 'Enviar solicitação'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.primary,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _TypeButton extends StatelessWidget {
  final String label;
  final IconData icon;
  final Color color;
  final bool selected;
  final VoidCallback onTap;

  const _TypeButton({
    required this.label,
    required this.icon,
    required this.color,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 150),
        padding: const EdgeInsets.symmetric(vertical: 14),
        decoration: BoxDecoration(
          color: selected ? color.withValues(alpha: 0.12) : const Color(0xFFF8FAFC),
          border: Border.all(
            color: selected ? color : const Color(0xFFE2E8F0),
            width: selected ? 2 : 1,
          ),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          children: [
            Icon(icon, color: selected ? color : AppColors.textHint, size: 24),
            const SizedBox(height: 4),
            Text(
              label,
              style: TextStyle(
                color: selected ? color : AppColors.textSecondary,
                fontWeight: selected ? FontWeight.bold : FontWeight.normal,
                fontSize: 14,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
