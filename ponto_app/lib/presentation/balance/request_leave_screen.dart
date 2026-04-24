import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import '../../core/theme/app_theme.dart';
import '../../data/datasources/hour_bank_datasource.dart';
import '../../data/models/hour_bank_request_model.dart';
import 'hour_bank_provider.dart';

class RequestLeaveScreen extends ConsumerStatefulWidget {
  final HourBankBalanceModel balance;

  const RequestLeaveScreen({super.key, required this.balance});

  @override
  ConsumerState<RequestLeaveScreen> createState() => _RequestLeaveScreenState();
}

class _RequestLeaveScreenState extends ConsumerState<RequestLeaveScreen> {
  final _formKey = GlobalKey<FormState>();
  DateTime? _selectedDate;
  int _hoursSelected = 1;
  int _minutesSelected = 0;
  final _justificationController = TextEditingController();
  bool _loading = false;
  String? _errorMessage;

  int get _totalMinutesRequested => (_hoursSelected * 60) + _minutesSelected;
  bool get _hasEnoughBalance =>
      _totalMinutesRequested <= widget.balance.totalMinutes;

  String get _previewBalance {
    final remaining = widget.balance.totalMinutes - _totalMinutesRequested;
    final sign = remaining >= 0 ? '+' : '-';
    final abs = remaining.abs();
    return '$sign${(abs ~/ 60).toString().padLeft(2, '0')}:${(abs % 60).toString().padLeft(2, '0')}';
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: DateTime.now().add(const Duration(days: 1)),
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 365)),
      locale: const Locale('pt', 'BR'),
      helpText: 'Escolha a data da folga',
    );
    if (picked != null) setState(() => _selectedDate = picked);
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_selectedDate == null) {
      setState(() => _errorMessage = 'Selecione a data da folga.');
      return;
    }
    if (!_hasEnoughBalance) {
      setState(() => _errorMessage = 'Saldo insuficiente para essa solicitação.');
      return;
    }

    setState(() {
      _loading = true;
      _errorMessage = null;
    });

    try {
      await ref.read(hourBankDatasourceProvider).createRequest(
            requestedDate: DateFormat('yyyy-MM-dd').format(_selectedDate!),
            minutesRequested: _totalMinutesRequested,
            justification: _justificationController.text.trim(),
          );

      ref.invalidate(hourBankRequestsProvider);
      ref.invalidate(hourBankBalanceProvider);

      if (mounted) {
        Navigator.of(context).pop(true);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Solicitação enviada! Aguarde a aprovação.'),
            backgroundColor: AppColors.success,
          ),
        );
      }
    } catch (e) {
      setState(() => _errorMessage = e.toString().replaceAll('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  void dispose() {
    _justificationController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Solicitar Folga'),
        backgroundColor: AppColors.surface,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Card saldo atual
              _SaldoCard(balance: widget.balance),

              const SizedBox(height: 24),

              // Seção data
              _SectionLabel(label: 'Data da folga'),
              const SizedBox(height: 8),
              GestureDetector(
                onTap: _pickDate,
                child: Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                  decoration: BoxDecoration(
                    color: AppColors.surface,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                      color: _selectedDate != null
                          ? AppColors.primary
                          : AppColors.divider,
                    ),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.calendar_today_outlined,
                          size: 18, color: AppColors.textSecondary),
                      const SizedBox(width: 12),
                      Text(
                        _selectedDate != null
                            ? DateFormat('dd/MM/yyyy (EEEE)', 'pt_BR')
                                .format(_selectedDate!)
                            : 'Selecione a data',
                        style: TextStyle(
                          fontSize: 14,
                          color: _selectedDate != null
                              ? AppColors.textPrimary
                              : AppColors.textHint,
                        ),
                      ),
                    ],
                  ),
                ),
              ),

              const SizedBox(height: 20),

              // Seção horas
              _SectionLabel(label: 'Quantidade de horas'),
              const SizedBox(height: 8),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: AppColors.surface,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: AppColors.divider),
                ),
                child: Column(
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: _HourPicker(
                            label: 'Horas',
                            value: _hoursSelected,
                            min: 0,
                            max: 8,
                            onChanged: (v) => setState(() => _hoursSelected = v),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: _HourPicker(
                            label: 'Minutos',
                            value: _minutesSelected,
                            min: 0,
                            max: 59,
                            step: 15,
                            onChanged: (v) => setState(() => _minutesSelected = v),
                          ),
                        ),
                      ],
                    ),
                    if (_totalMinutesRequested > 0) ...[
                      const SizedBox(height: 12),
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.symmetric(
                            horizontal: 12, vertical: 8),
                        decoration: BoxDecoration(
                          color: _hasEnoughBalance
                              ? AppColors.success.withValues(alpha: 0.1)
                              : AppColors.error.withValues(alpha: 0.1),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              'Saldo após desconto:',
                              style: TextStyle(
                                fontSize: 12,
                                color: _hasEnoughBalance
                                    ? AppColors.success
                                    : AppColors.error,
                              ),
                            ),
                            Text(
                              _previewBalance,
                              style: TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.bold,
                                color: _hasEnoughBalance
                                    ? AppColors.success
                                    : AppColors.error,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ],
                ),
              ),

              const SizedBox(height: 20),

              // Justificativa
              _SectionLabel(label: 'Justificativa'),
              const SizedBox(height: 8),
              TextFormField(
                controller: _justificationController,
                maxLines: 4,
                maxLength: 500,
                decoration: InputDecoration(
                  hintText: 'Descreva o motivo da solicitação de folga...',
                  filled: true,
                  fillColor: AppColors.surface,
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: BorderSide(color: AppColors.divider),
                  ),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: BorderSide(color: AppColors.divider),
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: AppColors.primary),
                  ),
                ),
                validator: (v) {
                  if (v == null || v.trim().length < 10) {
                    return 'Justificativa deve ter ao menos 10 caracteres.';
                  }
                  return null;
                },
              ),

              if (_errorMessage != null) ...[
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppColors.error.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(
                        color: AppColors.error.withValues(alpha: 0.3)),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.error_outline,
                          size: 16, color: AppColors.error),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(_errorMessage!,
                            style: const TextStyle(
                                fontSize: 13, color: AppColors.error)),
                      ),
                    ],
                  ),
                ),
              ],

              const SizedBox(height: 24),

              // Botão enviar
              SizedBox(
                width: double.infinity,
                height: 52,
                child: ElevatedButton(
                  onPressed: _loading ? null : _submit,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.primary,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14)),
                    elevation: 0,
                  ),
                  child: _loading
                      ? const SizedBox(
                          width: 22,
                          height: 22,
                          child: CircularProgressIndicator(
                              strokeWidth: 2, color: Colors.white),
                        )
                      : const Text('Enviar solicitação',
                          style: TextStyle(
                              fontSize: 15, fontWeight: FontWeight.w600)),
                ),
              ),

              const SizedBox(height: 40),
            ],
          ),
        ),
      ),
    );
  }
}

class _SaldoCard extends StatelessWidget {
  final HourBankBalanceModel balance;
  const _SaldoCard({required this.balance});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: balance.isPositive
              ? [const Color(0xFF059669), const Color(0xFF10B981)]
              : [const Color(0xFFDC2626), const Color(0xFFEF4444)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Saldo disponível',
              style: TextStyle(
                  color: Colors.white70, fontSize: 12, fontWeight: FontWeight.w500)),
          const SizedBox(height: 4),
          Text(balance.formatted,
              style: const TextStyle(
                  color: Colors.white,
                  fontSize: 36,
                  fontWeight: FontWeight.bold,
                  letterSpacing: 1)),
          const SizedBox(height: 8),
          Text(
            balance.isPositive
                ? '${(balance.totalMinutes ~/ 60)}h ${balance.totalMinutes % 60}min em crédito'
                : 'Saldo negativo — solicitações não permitidas',
            style: const TextStyle(color: Colors.white70, fontSize: 12),
          ),
        ],
      ),
    );
  }
}

class _SectionLabel extends StatelessWidget {
  final String label;
  const _SectionLabel({required this.label});

  @override
  Widget build(BuildContext context) => Text(
        label,
        style: const TextStyle(
          fontSize: 13,
          fontWeight: FontWeight.w600,
          color: AppColors.textSecondary,
        ),
      );
}

class _HourPicker extends StatelessWidget {
  final String label;
  final int value;
  final int min;
  final int max;
  final int step;
  final ValueChanged<int> onChanged;

  const _HourPicker({
    required this.label,
    required this.value,
    required this.min,
    required this.max,
    this.step = 1,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label,
            style: const TextStyle(
                fontSize: 12, color: AppColors.textSecondary)),
        const SizedBox(height: 6),
        Row(
          children: [
            _Btn(
              icon: Icons.remove,
              onTap: value - step >= min ? () => onChanged(value - step) : null,
            ),
            Expanded(
              child: Center(
                child: Text(
                  value.toString().padLeft(2, '0'),
                  style: const TextStyle(
                      fontSize: 22, fontWeight: FontWeight.bold),
                ),
              ),
            ),
            _Btn(
              icon: Icons.add,
              onTap: value + step <= max ? () => onChanged(value + step) : null,
            ),
          ],
        ),
      ],
    );
  }
}

class _Btn extends StatelessWidget {
  final IconData icon;
  final VoidCallback? onTap;
  const _Btn({required this.icon, this.onTap});

  @override
  Widget build(BuildContext context) => GestureDetector(
        onTap: onTap,
        child: Container(
          width: 36,
          height: 36,
          decoration: BoxDecoration(
            color: onTap != null
                ? AppColors.primary.withValues(alpha: 0.1)
                : AppColors.divider,
            borderRadius: BorderRadius.circular(8),
          ),
          child: Icon(icon,
              size: 18,
              color: onTap != null ? AppColors.primary : AppColors.textHint),
        ),
      );
}
