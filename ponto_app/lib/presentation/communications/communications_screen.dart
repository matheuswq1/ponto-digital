import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import '../../core/network/api_client.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/skeleton.dart';

// ─── Model ──────────────────────────────────────────────────────────────────

class CommunicationModel {
  final int id;
  final String title;
  final String body;
  final String type;
  final String typeLabel;
  final bool pinned;
  final String? publishedAt;

  const CommunicationModel({
    required this.id,
    required this.title,
    required this.body,
    required this.type,
    required this.typeLabel,
    required this.pinned,
    this.publishedAt,
  });

  factory CommunicationModel.fromJson(Map<String, dynamic> j) =>
      CommunicationModel(
        id: j['id'],
        title: j['title'] ?? '',
        body: j['body'] ?? '',
        type: j['type'] ?? 'info',
        typeLabel: j['type_label'] ?? 'Informativo',
        pinned: j['pinned'] == true,
        publishedAt: j['published_at'] as String?,
      );

  Color get typeColor => switch (type) {
        'urgente' => const Color(0xFFEF4444),
        'aviso'   => const Color(0xFFF59E0B),
        _         => AppColors.primary,
      };

  IconData get typeIcon => switch (type) {
        'urgente' => Icons.warning_amber_rounded,
        'aviso'   => Icons.notifications_active_rounded,
        _         => Icons.info_outline_rounded,
      };
}

// ─── Provider ───────────────────────────────────────────────────────────────

final communicationsProvider =
    FutureProvider.autoDispose<List<CommunicationModel>>((ref) async {
  final api = ref.read(apiClientProvider);
  final resp = await api.get('/communications');
  final data = resp.data['data'] as List<dynamic>;
  return data.map((e) => CommunicationModel.fromJson(e as Map<String, dynamic>)).toList();
});

// ─── Screen ─────────────────────────────────────────────────────────────────

class CommunicationsScreen extends ConsumerWidget {
  const CommunicationsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(communicationsProvider);

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        backgroundColor: AppColors.primary,
        foregroundColor: Colors.white,
        title: const Text('Comunicados', style: TextStyle(fontWeight: FontWeight.w600)),
        elevation: 0,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded, size: 20),
            onPressed: () => ref.invalidate(communicationsProvider),
          ),
        ],
      ),
      body: async.when(
        loading: () => _CommunicationsSkeleton(),
        error: (_, __) => Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.cloud_off_rounded, size: 48, color: AppColors.textHint),
              const SizedBox(height: 12),
              const Text('Erro ao carregar comunicados.', style: TextStyle(color: AppColors.textSecondary)),
              const SizedBox(height: 12),
              ElevatedButton.icon(
                onPressed: () => ref.invalidate(communicationsProvider),
                icon: const Icon(Icons.refresh, size: 18),
                label: const Text('Tentar novamente'),
              ),
            ],
          ),
        ),
        data: (items) => items.isEmpty
            ? _EmptyView()
            : ListView.separated(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
                itemCount: items.length,
                separatorBuilder: (_, __) => const SizedBox(height: 10),
                itemBuilder: (ctx, i) => _CommunicationCard(item: items[i]),
              ),
      ),
    );
  }
}

// ─── Card ────────────────────────────────────────────────────────────────────

class _CommunicationCard extends StatelessWidget {
  final CommunicationModel item;
  const _CommunicationCard({required this.item});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => _showDetail(context),
      child: Container(
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: item.pinned
                ? item.typeColor.withValues(alpha: 0.4)
                : AppColors.divider,
            width: item.pinned ? 1.5 : 1,
          ),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.04),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header colorido
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              decoration: BoxDecoration(
                color: item.typeColor.withValues(alpha: 0.06),
                borderRadius: const BorderRadius.vertical(top: Radius.circular(13)),
                border: Border(
                  bottom: BorderSide(color: item.typeColor.withValues(alpha: 0.15)),
                ),
              ),
              child: Row(
                children: [
                  Icon(item.typeIcon, color: item.typeColor, size: 16),
                  const SizedBox(width: 6),
                  Text(
                    item.typeLabel,
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                      color: item.typeColor,
                    ),
                  ),
                  if (item.pinned) ...[
                    const SizedBox(width: 6),
                    Icon(Icons.push_pin_rounded, size: 13, color: item.typeColor),
                  ],
                  const Spacer(),
                  if (item.publishedAt != null)
                    Text(
                      _formatDate(item.publishedAt!),
                      style: const TextStyle(fontSize: 11, color: AppColors.textHint),
                    ),
                ],
              ),
            ),
            // Conteúdo
            Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    item.title,
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      fontSize: 14,
                      color: AppColors.textPrimary,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    item.body,
                    maxLines: 3,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 13,
                      color: AppColors.textSecondary,
                      height: 1.5,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Toque para ler mais',
                    style: TextStyle(
                      fontSize: 11,
                      color: item.typeColor,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _showDetail(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _DetailSheet(item: item),
    );
  }

  String _formatDate(String iso) {
    try {
      final dt = DateTime.parse(iso).toLocal();
      return DateFormat('dd/MM/yyyy HH:mm').format(dt);
    } catch (_) {
      return '';
    }
  }
}

// ─── Bottom Sheet de detalhe ─────────────────────────────────────────────────

class _DetailSheet extends StatelessWidget {
  final CommunicationModel item;
  const _DetailSheet({required this.item});

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      initialChildSize: 0.65,
      maxChildSize: 0.95,
      minChildSize: 0.4,
      expand: false,
      builder: (_, ctrl) => Container(
        decoration: const BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Handle
            Center(
              child: Container(
                margin: const EdgeInsets.only(top: 12),
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: AppColors.divider,
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),
            // Header
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 0),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: item.typeColor.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(item.typeIcon, color: item.typeColor, size: 13),
                        const SizedBox(width: 4),
                        Text(item.typeLabel,
                            style: TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w700,
                                color: item.typeColor)),
                      ],
                    ),
                  ),
                  const Spacer(),
                  IconButton(
                    icon: const Icon(Icons.close_rounded, size: 20, color: AppColors.textHint),
                    onPressed: () => Navigator.pop(context),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
              child: Text(
                item.title,
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                  color: AppColors.textPrimary,
                ),
              ),
            ),
            const Divider(height: 1),
            // Corpo scrollável
            Expanded(
              child: ListView(
                controller: ctrl,
                padding: const EdgeInsets.all(20),
                children: [
                  Text(
                    item.body,
                    style: const TextStyle(
                      fontSize: 14,
                      color: AppColors.textSecondary,
                      height: 1.7,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _EmptyView extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            padding: const EdgeInsets.all(24),
            decoration: const BoxDecoration(
              color: AppColors.surfaceVariant,
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.campaign_rounded, size: 48, color: AppColors.textHint),
          ),
          const SizedBox(height: 20),
          const Text('Nenhum comunicado',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600, color: AppColors.textPrimary)),
          const SizedBox(height: 8),
          const Text(
            'Quando a empresa publicar\navisos, eles aparecerão aqui.',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 13, color: AppColors.textSecondary, height: 1.5),
          ),
        ],
      ),
    );
  }
}

class _CommunicationsSkeleton extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: 6,
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
                SkeletonShimmer(width: 32, height: 32, borderRadius: 10),
                const SizedBox(width: 10),
                Expanded(child: SkeletonShimmer(width: double.infinity, height: 13, borderRadius: 6)),
                const SizedBox(width: 8),
                SkeletonShimmer(width: 60, height: 11, borderRadius: 5),
              ],
            ),
            const SizedBox(height: 10),
            SkeletonShimmer(width: double.infinity, height: 11, borderRadius: 5),
            const SizedBox(height: 5),
            SkeletonShimmer(width: 200, height: 11, borderRadius: 5),
          ],
        ),
      ),
    );
  }
}
