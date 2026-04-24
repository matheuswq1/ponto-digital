import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'edit_requests_provider.dart';
import '../../data/models/time_record_edit_model.dart';
import '../../core/theme/app_theme.dart';

class EditRequestsScreen extends ConsumerWidget {
  const EditRequestsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(editRequestsProvider);

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Correções solicitadas'),
        centerTitle: true,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => ref.read(editRequestsProvider.notifier).refresh(),
          ),
        ],
      ),
      body: _body(context, ref, state),
    );
  }

  Widget _body(BuildContext context, WidgetRef ref, EditRequestsState state) {
    if (state.isLoading && state.items.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.error != null && state.items.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.error_outline, size: 48, color: AppColors.error),
              const SizedBox(height: 12),
              Text(state.error!,
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: AppColors.textSecondary)),
              const SizedBox(height: 20),
              FilledButton.icon(
                onPressed: () =>
                    ref.read(editRequestsProvider.notifier).refresh(),
                icon: const Icon(Icons.refresh),
                label: const Text('Tentar novamente'),
              ),
            ],
          ),
        ),
      );
    }

    if (state.items.isEmpty) {
      return const Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.edit_off_outlined, size: 64, color: AppColors.textHint),
            SizedBox(height: 12),
            Text(
              'Nenhuma solicitação ainda.',
              style: TextStyle(
                  color: AppColors.textSecondary, fontSize: 15),
            ),
            SizedBox(height: 4),
            Text(
              'As correções de ponto aparecem aqui.',
              style: TextStyle(color: AppColors.textHint, fontSize: 13),
            ),
          ],
        ),
      );
    }

    return NotificationListener<ScrollNotification>(
      onNotification: (n) {
        if (n.metrics.pixels >= n.metrics.maxScrollExtent - 200) {
          ref.read(editRequestsProvider.notifier).loadMore();
        }
        return false;
      },
      child: RefreshIndicator(
        onRefresh: () async =>
            ref.read(editRequestsProvider.notifier).refresh(),
        child: ListView.builder(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
          itemCount: state.items.length + (state.hasMore ? 1 : 0),
          itemBuilder: (context, i) {
            if (i >= state.items.length) {
              return const Padding(
                padding: EdgeInsets.symmetric(vertical: 20),
                child: Center(child: CircularProgressIndicator()),
              );
            }
            return _EditRequestCard(item: state.items[i]);
          },
        ),
      ),
    );
  }
}

// ─── Card de solicitação ──────────────────────────────────────────────────────

class _EditRequestCard extends StatelessWidget {
  final TimeRecordEditModel item;
  const _EditRequestCard({required this.item});

  static final _fmt = DateFormat('dd/MM/yyyy HH:mm', 'pt_BR');

  String _fmtDate(DateTime? dt) =>
      dt != null ? _fmt.format(dt) : '—';

  String _typeLabel(String? type) => switch (type) {
        'entrada' => 'Entrada',
        'saida' => 'Saída',
        _ => type ?? '—',
      };

  Color _typeColor(String? type) => switch (type) {
        'entrada' => AppColors.entrada,
        'saida' => AppColors.saida,
        _ => AppColors.primary,
      };

  @override
  Widget build(BuildContext context) {
    final statusColor = switch (item.status) {
      'aprovado' => AppColors.success,
      'rejeitado' => AppColors.error,
      _ => AppColors.warning,
    };

    final createdStr = item.createdAt != null
        ? _fmt.format(
            DateTime.tryParse(item.createdAt!)?.toLocal() ?? DateTime.now())
        : '';

    final typeChanged = item.newType != null && item.newType != item.originalType;
    final dateChanged = item.newDatetime != null &&
        item.newDatetime != item.originalDatetime;

    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.divider),
        boxShadow: const [
          BoxShadow(color: AppColors.shadow, blurRadius: 8, offset: Offset(0, 2)),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // ── Header: status + data da solicitação ──────────────────────
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 0),
            child: Row(
              children: [
                _StatusChip(status: item.status, label: item.statusLabel),
                const Spacer(),
                Icon(Icons.access_time, size: 12, color: AppColors.textHint),
                const SizedBox(width: 4),
                Text(
                  createdStr,
                  style: const TextStyle(
                      fontSize: 11, color: AppColors.textHint),
                ),
              ],
            ),
          ),

          const SizedBox(height: 12),
          const Divider(height: 1, color: AppColors.divider),
          const SizedBox(height: 12),

          // ── De: horário/tipo original ─────────────────────────────────
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Row(
              children: [
                const _RowIcon(icon: Icons.history, color: AppColors.textHint),
                const SizedBox(width: 10),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Original',
                        style: TextStyle(
                            fontSize: 10,
                            color: AppColors.textHint,
                            fontWeight: FontWeight.w600)),
                    const SizedBox(height: 2),
                    Row(
                      children: [
                        _TypeBadge(
                          label: _typeLabel(item.originalType),
                          color: _typeColor(item.originalType),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          _fmtDate(item.originalDatetime),
                          style: const TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w600,
                            color: AppColors.textPrimary,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ],
            ),
          ),

          // ── Seta de mudança ───────────────────────────────────────────
          if (dateChanged || typeChanged) ...[
            Padding(
              padding: const EdgeInsets.only(left: 20, top: 6, bottom: 2),
              child: Icon(Icons.arrow_downward,
                  size: 16, color: statusColor.withValues(alpha: 0.6)),
            ),

            // ── Para: novo horário/tipo ─────────────────────────────────
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Row(
                children: [
                  _RowIcon(icon: Icons.edit_note, color: statusColor),
                  const SizedBox(width: 10),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Solicitado',
                          style: TextStyle(
                              fontSize: 10,
                              color: AppColors.textHint,
                              fontWeight: FontWeight.w600)),
                      const SizedBox(height: 2),
                      Row(
                        children: [
                          _TypeBadge(
                            label: _typeLabel(item.newType ?? item.originalType),
                            color: _typeColor(item.newType ?? item.originalType),
                          ),
                          const SizedBox(width: 8),
                          Text(
                            _fmtDate(item.newDatetime ?? item.originalDatetime),
                            style: TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w600,
                              color: statusColor,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],

          const SizedBox(height: 12),
          const Divider(height: 1, color: AppColors.divider),

          // ── Justificativa ─────────────────────────────────────────────
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'JUSTIFICATIVA',
                  style: TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textHint,
                    letterSpacing: 0.6,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  item.justification,
                  style: const TextStyle(
                    fontSize: 13,
                    color: AppColors.textSecondary,
                    height: 1.4,
                  ),
                ),

                // ── Nota do gestor (se houver) ──────────────────────────
                if (item.approvalNotes != null &&
                    item.approvalNotes!.isNotEmpty) ...[
                  const SizedBox(height: 10),
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: statusColor.withValues(alpha: 0.07),
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(
                          color: statusColor.withValues(alpha: 0.2)),
                    ),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(
                          item.status == 'aprovado'
                              ? Icons.check_circle_outline
                              : Icons.cancel_outlined,
                          color: statusColor,
                          size: 15,
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            item.approvalNotes!,
                            style: TextStyle(
                                fontSize: 12,
                                color: statusColor,
                                fontWeight: FontWeight.w500),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Widgets auxiliares ───────────────────────────────────────────────────────

class _StatusChip extends StatelessWidget {
  final String status;
  final String label;
  const _StatusChip({required this.status, required this.label});

  @override
  Widget build(BuildContext context) {
    final color = switch (status) {
      'aprovado' => AppColors.success,
      'rejeitado' => AppColors.error,
      _ => AppColors.warning,
    };
    final icon = switch (status) {
      'aprovado' => Icons.check_circle,
      'rejeitado' => Icons.cancel,
      _ => Icons.hourglass_top,
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 13, color: color),
          const SizedBox(width: 5),
          Text(label,
              style: TextStyle(
                  color: color,
                  fontSize: 12,
                  fontWeight: FontWeight.w700)),
        ],
      ),
    );
  }
}

class _TypeBadge extends StatelessWidget {
  final String label;
  final Color color;
  const _TypeBadge({required this.label, required this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(label,
          style: TextStyle(
              color: color, fontSize: 11, fontWeight: FontWeight.bold)),
    );
  }
}

class _RowIcon extends StatelessWidget {
  final IconData icon;
  final Color color;
  const _RowIcon({required this.icon, required this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 32,
      height: 32,
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        shape: BoxShape.circle,
      ),
      child: Icon(icon, size: 16, color: color),
    );
  }
}
