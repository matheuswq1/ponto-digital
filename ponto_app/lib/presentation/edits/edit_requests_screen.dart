import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'edit_requests_provider.dart';
import '../../core/theme/app_theme.dart';

class EditRequestsScreen extends ConsumerWidget {
  const EditRequestsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(editRequestsProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Solicitações de correção'),
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
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(state.error!, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            FilledButton(
              onPressed: () => ref.read(editRequestsProvider.notifier).refresh(),
              child: const Text('Tentar novamente'),
            ),
          ],
        ),
      );
    }
    if (state.items.isEmpty) {
      return const Center(
        child: Text(
          'Nenhuma solicitação ainda.',
          style: TextStyle(color: AppColors.textSecondary),
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
        onRefresh: () async => ref.read(editRequestsProvider.notifier).refresh(),
        child: ListView.builder(
          padding: const EdgeInsets.all(16),
          itemCount: state.items.length + (state.hasMore ? 1 : 0),
          itemBuilder: (context, i) {
            if (i >= state.items.length) {
              return const Padding(
                padding: EdgeInsets.symmetric(vertical: 20),
                child: Center(child: CircularProgressIndicator()),
              );
            }
            final e = state.items[i];
            final created = e.createdAt != null
                ? DateFormat("dd/MM/yyyy HH:mm", 'pt_BR')
                    .format(DateTime.tryParse(e.createdAt!)?.toLocal() ?? DateTime.now())
                : '';
            return Card(
              margin: const EdgeInsets.only(bottom: 12),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        _StatusChip(status: e.status, label: e.statusLabel),
                        const Spacer(),
                        Text(created, style: const TextStyle(fontSize: 12, color: AppColors.textHint)),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Text(
                      e.justification,
                      style: const TextStyle(fontSize: 14, color: AppColors.textPrimary),
                    ),
                    if (e.approvalNotes != null && e.approvalNotes!.isNotEmpty) ...[
                      const SizedBox(height: 8),
                      Text(
                        'Observação: ${e.approvalNotes}',
                        style: const TextStyle(fontSize: 12, color: AppColors.textSecondary),
                      ),
                    ],
                  ],
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}

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
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(label, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w600)),
    );
  }
}
