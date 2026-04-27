import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'history_provider.dart';
import '../../data/models/time_record_model.dart';
import '../../core/theme/app_theme.dart';

class HistoryScreen extends ConsumerWidget {
  const HistoryScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(historyProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Histórico de Pontos'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => ref.read(historyProvider.notifier).refresh(),
          ),
        ],
      ),
      body: _buildBody(context, ref, state),
    );
  }

  Widget _buildBody(BuildContext context, WidgetRef ref, HistoryState state) {
    if (state.isLoading && state.records.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.error != null && state.records.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 48, color: AppColors.error),
            const SizedBox(height: 12),
            Text(state.error!, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: () => ref.read(historyProvider.notifier).refresh(),
              child: const Text('Tentar novamente'),
            ),
          ],
        ),
      );
    }

    if (state.records.isEmpty) {
      return const Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.history, size: 64, color: AppColors.textHint),
            SizedBox(height: 12),
            Text('Nenhum registro encontrado.',
                style: TextStyle(color: AppColors.textSecondary)),
          ],
        ),
      );
    }

    // Agrupar por data
    final grouped = <String, List<TimeRecordModel>>{};
    for (final record in state.records) {
      final key = DateFormat('yyyy-MM-dd').format(record.datetime);
      grouped.putIfAbsent(key, () => []).add(record);
    }

    return NotificationListener<ScrollNotification>(
      onNotification: (notification) {
        if (notification.metrics.pixels >= notification.metrics.maxScrollExtent - 200) {
          ref.read(historyProvider.notifier).loadMore();
        }
        return false;
      },
      child: RefreshIndicator(
        onRefresh: () async => ref.read(historyProvider.notifier).refresh(),
        child: ListView.builder(
          padding: const EdgeInsets.all(16),
          itemCount: grouped.length + (state.hasMore ? 1 : 0),
          itemBuilder: (context, index) {
            if (index >= grouped.length) {
              return const Padding(
                padding: EdgeInsets.symmetric(vertical: 20),
                child: Center(child: CircularProgressIndicator()),
              );
            }

            final dateKey = grouped.keys.elementAt(index);
            final records = grouped[dateKey]!;
            final date = DateTime.parse(dateKey);

            return _DayGroup(
              date: date,
              records: records,
              onRecordTap: (record) => _onRecordTap(context, record),
            );
          },
        ),
      ),
    );
  }

  void _onRecordTap(BuildContext context, TimeRecordModel record) {
    if (record.id == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Sincronize o ponto offline antes de pedir correção.')),
      );
      return;
    }
    if (record.isEdited) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Este registro já possui correção processada.')),
      );
      return;
    }
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (record.hasPendingEdit)
              Container(
                margin: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                decoration: BoxDecoration(
                  color: AppColors.warning.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: AppColors.warning.withValues(alpha: 0.4)),
                ),
                child: const Row(
                  children: [
                    Icon(Icons.hourglass_top_rounded, color: AppColors.warning, size: 16),
                    SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        'Já existe uma solicitação pendente para este ponto. Aguarde a resposta do gestor.',
                        style: TextStyle(fontSize: 13, color: AppColors.warning),
                      ),
                    ),
                  ],
                ),
              ),
            ListTile(
              leading: Icon(
                Icons.edit_note,
                color: record.hasPendingEdit ? AppColors.textHint : AppColors.primary,
              ),
              title: Text(
                'Solicitar correção',
                style: TextStyle(
                  color: record.hasPendingEdit ? AppColors.textHint : AppColors.textPrimary,
                ),
              ),
              subtitle: Text(
                record.hasPendingEdit
                    ? 'Solicitação já enviada — aguardando aprovação'
                    : 'Peça ajuste de horário/tipo ao gestor',
                style: TextStyle(
                  color: record.hasPendingEdit ? AppColors.warning : AppColors.textSecondary,
                  fontWeight: record.hasPendingEdit ? FontWeight.w500 : FontWeight.normal,
                ),
              ),
              onTap: record.hasPendingEdit
                  ? null // desativado
                  : () {
                      Navigator.pop(ctx);
                      context.pushNamed('request-edit', extra: record);
                    },
            ),
            ListTile(
              leading: const Icon(Icons.add_circle_outline, color: AppColors.primary),
              title: const Text('Adicionar ponto ao dia'),
              subtitle: const Text('Solicitar adição de entrada ou saída esquecida'),
              onTap: () {
                Navigator.pop(ctx);
                // Sugere a data do registo para pré-preencher
                context.pushNamed('request-add-point', extra: record.datetime);
              },
            ),
            if (record.photoUrl != null)
              ListTile(
                leading: const Icon(Icons.image_outlined),
                title: const Text('Foto do registro'),
                onTap: () {
                  Navigator.pop(ctx);
                  showDialog<void>(
                    context: context,
                    builder: (c) => Dialog(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Padding(
                            padding: const EdgeInsets.all(8),
                            child: Image.network(
                              record.photoUrl!,
                              errorBuilder: (_, __, ___) => const Text('Não foi possível carregar a imagem'),
                            ),
                          ),
                        ],
                      ),
                    ),
                  );
                },
              ),
          ],
        ),
      ),
    );
  }
}

class _DayGroup extends StatelessWidget {
  final DateTime date;
  final List<TimeRecordModel> records;
  final void Function(TimeRecordModel) onRecordTap;

  const _DayGroup({
    required this.date,
    required this.records,
    required this.onRecordTap,
  });

  /// Calcula horas trabalhadas somando pares entrada/saída.
  ({String worked, String? firstIn, String? lastOut}) _calcDay() {
    final entries = records.where((r) => r.type == 'entrada').toList()
      ..sort((a, b) => a.datetime.compareTo(b.datetime));
    final exits = records.where((r) => r.type == 'saida').toList()
      ..sort((a, b) => a.datetime.compareTo(b.datetime));

    int totalMin = 0;
    for (int i = 0; i < entries.length; i++) {
      if (i < exits.length) {
        final diff = exits[i].datetime.difference(entries[i].datetime).inMinutes;
        if (diff > 0) totalMin += diff;
      }
    }

    String fmtMin(int m) =>
        '${(m ~/ 60).toString().padLeft(2, '0')}h${(m % 60).toString().padLeft(2, '0')}';

    final firstIn = entries.isNotEmpty
        ? entries.first.datetimeLocal.split(' ').last.substring(0, 5)
        : null;
    final lastOut = exits.isNotEmpty
        ? exits.last.datetimeLocal.split(' ').last.substring(0, 5)
        : null;

    return (
      worked: totalMin > 0 ? fmtMin(totalMin) : '—',
      firstIn: firstIn,
      lastOut: lastOut,
    );
  }

  @override
  Widget build(BuildContext context) {
    final isToday = DateFormat('yyyy-MM-dd').format(date) ==
        DateFormat('yyyy-MM-dd').format(DateTime.now());
    final isYesterday = DateFormat('yyyy-MM-dd').format(date) ==
        DateFormat('yyyy-MM-dd').format(DateTime.now().subtract(const Duration(days: 1)));

    String dateLabel;
    if (isToday) {
      dateLabel = 'Hoje';
    } else if (isYesterday) {
      dateLabel = 'Ontem';
    } else {
      dateLabel = DateFormat("EEEE, d 'de' MMMM", 'pt_BR').format(date);
    }

    final summary = _calcDay();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(bottom: 8, top: 4),
          child: Row(
            children: [
              Text(
                dateLabel,
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.bold,
                  color: AppColors.textSecondary,
                ),
              ),
              const SizedBox(width: 8),
              Text(
                DateFormat('dd/MM', 'pt_BR').format(date),
                style: const TextStyle(fontSize: 12, color: AppColors.textHint),
              ),
              const Spacer(),
              // Resumo: entrada → saída | tempo trabalhado
              if (summary.firstIn != null)
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.login, size: 12, color: AppColors.entrada),
                    const SizedBox(width: 2),
                    Text(summary.firstIn!,
                        style: const TextStyle(fontSize: 11, color: AppColors.entrada, fontWeight: FontWeight.w600)),
                    if (summary.lastOut != null) ...[
                      const SizedBox(width: 6),
                      const Icon(Icons.logout, size: 12, color: AppColors.saida),
                      const SizedBox(width: 2),
                      Text(summary.lastOut!,
                          style: const TextStyle(fontSize: 11, color: AppColors.saida, fontWeight: FontWeight.w600)),
                    ],
                    const SizedBox(width: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                      decoration: BoxDecoration(
                        color: AppColors.primary.withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Text(
                        summary.worked,
                        style: const TextStyle(fontSize: 11, color: AppColors.primary, fontWeight: FontWeight.bold),
                      ),
                    ),
                  ],
                ),
            ],
          ),
        ),
        Container(
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: AppColors.divider),
          ),
          child: Column(
            children: records.asMap().entries.map((entry) {
              final i = entry.key;
              final record = entry.value;
              return Column(
                children: [
                  _RecordItem(record: record, onTap: () => onRecordTap(record)),
                  if (i < records.length - 1)
                    const Divider(height: 1, color: AppColors.divider, indent: 56),
                ],
              );
            }).toList(),
          ),
        ),
        const SizedBox(height: 16),
      ],
    );
  }
}

class _RecordItem extends StatelessWidget {
  final TimeRecordModel record;
  final VoidCallback onTap;

  const _RecordItem({required this.record, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final color = switch (record.type) {
      'entrada' => AppColors.entrada,
      'saida' => AppColors.saida,
      _ => AppColors.primary,
    };
    final time = record.datetimeLocal.split(' ').last.substring(0, 5);

    return ListTile(
      onTap: onTap,
      leading: Container(
        width: 40,
        height: 40,
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.1),
          shape: BoxShape.circle,
        ),
        child: Icon(_typeIcon(record.type), color: color, size: 20),
      ),
      title: Text(
        record.typeLabel,
        style: const TextStyle(
          fontWeight: FontWeight.w600,
          fontSize: 14,
          color: AppColors.textPrimary,
        ),
      ),
      subtitle: Row(
        children: [
          if (record.offline)
            const _Tag(label: 'Offline', color: AppColors.warning),
          if (record.hasPendingEdit)
            const _Tag(label: 'Correção pendente', color: AppColors.warning),
          if (record.isEdited)
            const _Tag(label: 'Editado', color: AppColors.info),
          if (record.isMockLocation)
            const _Tag(label: 'GPS falso', color: AppColors.error),
        ],
      ),
      trailing: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Text(
            time,
            style: const TextStyle(
              fontWeight: FontWeight.bold,
              fontSize: 18,
              color: AppColors.textPrimary,
            ),
          ),
          if (record.photoUrl != null)
            const Icon(Icons.photo_camera, size: 13, color: AppColors.textHint),
        ],
      ),
    );
  }

  IconData _typeIcon(String type) => switch (type) {
        'entrada' => Icons.login,
        'saida' => Icons.logout,
        _ => Icons.access_time,
      };
}

class _Tag extends StatelessWidget {
  final String label;
  final Color color;
  const _Tag({required this.label, required this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(right: 4),
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(4),
      ),
      child: Text(label, style: TextStyle(color: color, fontSize: 10)),
    );
  }
}

