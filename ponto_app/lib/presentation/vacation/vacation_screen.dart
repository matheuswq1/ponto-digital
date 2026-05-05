import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import '../../core/network/api_client.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/skeleton.dart';

// ─── Model ──────────────────────────────────────────────────────────────────

class VacationRequestModel {
  final int id;
  final String startDate;
  final String endDate;
  final int days;
  final String? reason;
  final String status;
  final String statusLabel;
  final String? reviewNotes;
  final String createdAt;

  const VacationRequestModel({
    required this.id,
    required this.startDate,
    required this.endDate,
    required this.days,
    this.reason,
    required this.status,
    required this.statusLabel,
    this.reviewNotes,
    required this.createdAt,
  });

  factory VacationRequestModel.fromJson(Map<String, dynamic> j) =>
      VacationRequestModel(
        id: j['id'],
        startDate: j['start_date'] ?? '',
        endDate: j['end_date'] ?? '',
        days: j['days'] ?? 0,
        reason: j['reason'] as String?,
        status: j['status'] ?? 'pendente',
        statusLabel: j['status_label'] ?? 'Pendente',
        reviewNotes: j['review_notes'] as String?,
        createdAt: j['created_at'] ?? '',
      );

  Color get statusColor => switch (status) {
        'aprovado'  => AppColors.success,
        'rejeitado' => AppColors.error,
        _           => AppColors.warning,
      };

  IconData get statusIcon => switch (status) {
        'aprovado'  => Icons.check_circle_rounded,
        'rejeitado' => Icons.cancel_rounded,
        _           => Icons.schedule_rounded,
      };
}

// ─── Providers ───────────────────────────────────────────────────────────────

final vacationListProvider =
    FutureProvider.autoDispose<List<VacationRequestModel>>((ref) async {
  final api = ref.read(apiClientProvider);
  final resp = await api.get('/vacation-requests');
  final data = resp.data['data'] as List<dynamic>;
  return data.map((e) => VacationRequestModel.fromJson(e as Map<String, dynamic>)).toList();
});

// ─── Screen ─────────────────────────────────────────────────────────────────

class VacationScreen extends ConsumerWidget {
  const VacationScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(vacationListProvider);

    return Scaffold(
      appBar: AppBar(
        backgroundColor: AppColors.primary,
        foregroundColor: Colors.white,
        title: const Text('Férias', style: TextStyle(fontWeight: FontWeight.w600)),
        elevation: 0,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded, size: 20),
            onPressed: () => ref.invalidate(vacationListProvider),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _showNewRequestSheet(context, ref),
        backgroundColor: AppColors.primary,
        foregroundColor: Colors.white,
        icon: const Icon(Icons.add_rounded, size: 20),
        label: const Text('Solicitar férias', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
      ),
      body: async.when(
        loading: () => _VacationSkeleton(),
        error: (_, __) => Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.cloud_off_rounded, size: 48, color: AppColors.textHint),
              const SizedBox(height: 12),
              const Text('Erro ao carregar.', style: TextStyle(color: AppColors.textSecondary)),
              const SizedBox(height: 12),
              ElevatedButton.icon(
                onPressed: () => ref.invalidate(vacationListProvider),
                icon: const Icon(Icons.refresh, size: 18),
                label: const Text('Tentar novamente'),
              ),
            ],
          ),
        ),
        data: (items) => items.isEmpty
            ? _EmptyView(onNew: () => _showNewRequestSheet(context, ref))
            : ListView.separated(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
                itemCount: items.length,
                separatorBuilder: (_, __) => const SizedBox(height: 10),
                itemBuilder: (_, i) => _VacationCard(
                  item: items[i],
                  onCancel: () async {
                    await _cancelRequest(context, ref, items[i].id);
                  },
                ),
              ),
      ),
    );
  }

  void _showNewRequestSheet(BuildContext context, WidgetRef ref) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _NewRequestSheet(onSubmitted: () => ref.invalidate(vacationListProvider)),
    );
  }

  Future<void> _cancelRequest(BuildContext context, WidgetRef ref, int id) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Cancelar solicitação'),
        content: const Text('Deseja cancelar esta solicitação de férias?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Não')),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Sim, cancelar', style: TextStyle(color: AppColors.error)),
          ),
        ],
      ),
    );

    if (confirm != true || !context.mounted) return;

    try {
      final api = ref.read(apiClientProvider);
      await api.delete('/vacation-requests/$id');
      ref.invalidate(vacationListProvider);
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Solicitação cancelada.'), backgroundColor: AppColors.success),
        );
      }
    } catch (_) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Não foi possível cancelar.'), backgroundColor: AppColors.error),
        );
      }
    }
  }
}

// ─── Card de solicitação ──────────────────────────────────────────────────────

class _VacationCard extends StatelessWidget {
  final VacationRequestModel item;
  final VoidCallback onCancel;

  const _VacationCard({required this.item, required this.onCancel});

  @override
  Widget build(BuildContext context) {
    final start = _fmt(item.startDate);
    final end   = _fmt(item.endDate);

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.divider),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 8, offset: const Offset(0, 2))],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: const Color(0xFFF59E0B).withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Icon(Icons.beach_access_rounded, color: Color(0xFFF59E0B), size: 20),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('$start → $end',
                        style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: AppColors.textPrimary)),
                    Text('${item.days} dias úteis',
                        style: const TextStyle(fontSize: 12, color: AppColors.textSecondary)),
                  ],
                ),
              ),
              // Badge status
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: item.statusColor.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(item.statusIcon, color: item.statusColor, size: 12),
                    const SizedBox(width: 4),
                    Text(item.statusLabel,
                        style: TextStyle(
                            fontSize: 11, fontWeight: FontWeight.w700, color: item.statusColor)),
                  ],
                ),
              ),
            ],
          ),
          if (item.reason?.isNotEmpty == true) ...[
            const SizedBox(height: 10),
            const Divider(height: 1),
            const SizedBox(height: 10),
            Text(item.reason!,
                style: const TextStyle(fontSize: 12, color: AppColors.textSecondary, height: 1.4)),
          ],
          if (item.reviewNotes?.isNotEmpty == true) ...[
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: item.statusColor.withValues(alpha: 0.06),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: item.statusColor.withValues(alpha: 0.2)),
              ),
              child: Text(
                'Resposta: ${item.reviewNotes}',
                style: TextStyle(fontSize: 12, color: item.statusColor, height: 1.4),
              ),
            ),
          ],
          if (item.status == 'pendente') ...[
            const SizedBox(height: 10),
            Align(
              alignment: Alignment.centerRight,
              child: TextButton.icon(
                onPressed: onCancel,
                icon: const Icon(Icons.close_rounded, size: 14, color: AppColors.error),
                label: const Text('Cancelar solicitação',
                    style: TextStyle(fontSize: 12, color: AppColors.error)),
                style: TextButton.styleFrom(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4)),
              ),
            ),
          ],
        ],
      ),
    );
  }

  String _fmt(String d) {
    try {
      return DateFormat('dd/MM/yyyy').format(DateTime.parse(d));
    } catch (_) {
      return d;
    }
  }
}

// ─── Sheet nova solicitação ───────────────────────────────────────────────────

class _NewRequestSheet extends ConsumerStatefulWidget {
  final VoidCallback onSubmitted;
  const _NewRequestSheet({required this.onSubmitted});

  @override
  ConsumerState<_NewRequestSheet> createState() => _NewRequestSheetState();
}

class _NewRequestSheetState extends ConsumerState<_NewRequestSheet> {
  DateTime? _start;
  DateTime? _end;
  final _reasonCtrl = TextEditingController();
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _reasonCtrl.dispose();
    super.dispose();
  }

  int get _days {
    if (_start == null || _end == null) return 0;
    int count = 0;
    var d = _start!;
    while (!d.isAfter(_end!)) {
      if (d.weekday != DateTime.saturday && d.weekday != DateTime.sunday) count++;
      d = d.add(const Duration(days: 1));
    }
    return count;
  }

  Future<void> _pick(bool isStart) async {
    final initial = isStart ? (_start ?? DateTime.now()) : (_end ?? (_start ?? DateTime.now()));
    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 365)),
      locale: const Locale('pt', 'BR'),
    );
    if (picked == null) return;
    setState(() {
      if (isStart) {
        _start = picked;
        if (_end != null && _end!.isBefore(picked)) _end = null;
      } else {
        _end = picked;
      }
    });
  }

  Future<void> _submit() async {
    if (_start == null || _end == null) {
      setState(() => _error = 'Selecione o período.');
      return;
    }
    if (_days == 0) {
      setState(() => _error = 'O período selecionado não contém dias úteis.');
      return;
    }
    setState(() { _loading = true; _error = null; });
    try {
      final api = ref.read(apiClientProvider);
      await api.post('/vacation-requests', data: {
        'start_date': DateFormat('yyyy-MM-dd').format(_start!),
        'end_date':   DateFormat('yyyy-MM-dd').format(_end!),
        'reason':     _reasonCtrl.text.trim().isEmpty ? null : _reasonCtrl.text.trim(),
      });
      if (mounted) Navigator.pop(context);
      widget.onSubmitted();
    } catch (e) {
      setState(() { _error = 'Não foi possível enviar a solicitação.'; _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    final fmt = DateFormat('dd/MM/yyyy');
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: Container(
        decoration: const BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        padding: const EdgeInsets.fromLTRB(20, 0, 20, 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Center(
              child: Container(
                margin: const EdgeInsets.only(top: 12, bottom: 16),
                width: 40, height: 4,
                decoration: BoxDecoration(color: AppColors.divider, borderRadius: BorderRadius.circular(2)),
              ),
            ),
            const Text('Solicitar férias',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppColors.textPrimary)),
            const SizedBox(height: 20),

            // Seleção de datas
            Row(
              children: [
                Expanded(child: _datePicker('Início', _start == null ? null : fmt.format(_start!), () => _pick(true))),
                const SizedBox(width: 12),
                Expanded(child: _datePicker('Fim', _end == null ? null : fmt.format(_end!), () => _pick(false))),
              ],
            ),

            // Contador de dias
            if (_days > 0) ...[
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.06),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: AppColors.primary.withValues(alpha: 0.15)),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.calendar_today_rounded, size: 16, color: AppColors.primary),
                    const SizedBox(width: 8),
                    Text('$_days dias úteis selecionados',
                        style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.primary)),
                  ],
                ),
              ),
            ],

            const SizedBox(height: 14),

            // Observação
            TextField(
              controller: _reasonCtrl,
              maxLines: 3,
              maxLength: 500,
              decoration: InputDecoration(
                labelText: 'Observação (opcional)',
                alignLabelWithHint: true,
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
              ),
            ),

            if (_error != null) ...[
              const SizedBox(height: 8),
              Text(_error!, style: const TextStyle(color: AppColors.error, fontSize: 13)),
            ],

            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: _loading ? null : _submit,
                child: _loading
                    ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                    : const Text('Enviar solicitação'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _datePicker(String label, String? value, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
        decoration: BoxDecoration(
          color: AppColors.surfaceVariant,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: AppColors.divider),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: const TextStyle(fontSize: 10, color: AppColors.textHint, fontWeight: FontWeight.w600)),
            const SizedBox(height: 4),
            Row(
              children: [
                const Icon(Icons.calendar_month_rounded, size: 14, color: AppColors.primary),
                const SizedBox(width: 6),
                Text(
                  value ?? 'Selecionar',
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: value != null ? AppColors.textPrimary : AppColors.textHint,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _EmptyView extends StatelessWidget {
  final VoidCallback onNew;
  const _EmptyView({required this.onNew});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              padding: const EdgeInsets.all(24),
              decoration: const BoxDecoration(color: AppColors.surfaceVariant, shape: BoxShape.circle),
              child: const Icon(Icons.beach_access_rounded, size: 48, color: AppColors.textHint),
            ),
            const SizedBox(height: 20),
            const Text('Nenhuma solicitação',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600, color: AppColors.textPrimary)),
            const SizedBox(height: 8),
            const Text(
              'Solicite férias tocando no botão abaixo.',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 13, color: AppColors.textSecondary, height: 1.5),
            ),
            const SizedBox(height: 20),
            ElevatedButton.icon(
              onPressed: onNew,
              icon: const Icon(Icons.add_rounded, size: 18),
              label: const Text('Solicitar férias'),
            ),
          ],
        ),
      ),
    );
  }
}

class _VacationSkeleton extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: 5,
      separatorBuilder: (_, __) => const SizedBox(height: 12),
      itemBuilder: (_, __) => Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: AppColors.divider),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                SkeletonShimmer(width: 90, height: 13, borderRadius: 6),
                const Spacer(),
                SkeletonShimmer(width: 60, height: 22, borderRadius: 11),
              ],
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                SkeletonShimmer(width: 130, height: 11, borderRadius: 5),
                const SizedBox(width: 12),
                SkeletonShimmer(width: 80, height: 11, borderRadius: 5),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
