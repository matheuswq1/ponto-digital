import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/skeleton.dart';
import '../../data/datasources/payslip_datasource.dart';
import '../../data/models/payslip_model.dart';

// ─── Provider ───────────────────────────────────────────────────────────────

final _selectedYearProvider = StateProvider<int>((ref) => DateTime.now().year);

final payslipsProvider = FutureProvider.autoDispose.family<List<PayslipModel>, int>(
  (ref, year) => ref.read(payslipDatasourceProvider).list(year: year),
);

// ─── Screen ─────────────────────────────────────────────────────────────────

class PayslipsScreen extends ConsumerWidget {
  const PayslipsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final year = ref.watch(_selectedYearProvider);
    final payslipsAsync = ref.watch(payslipsProvider(year));
    final currentYear = DateTime.now().year;
    final years = List.generate(5, (i) => currentYear - i);

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        backgroundColor: AppColors.primary,
        foregroundColor: Colors.white,
        title: const Text('Holerites', style: TextStyle(fontWeight: FontWeight.w600)),
        elevation: 0,
        actions: [
          // Seletor de ano
          Padding(
            padding: const EdgeInsets.only(right: 12),
            child: DropdownButtonHideUnderline(
              child: DropdownButton<int>(
                value: year,
                dropdownColor: AppColors.primaryDark,
                iconEnabledColor: Colors.white,
                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600, fontSize: 14),
                items: years.map((y) => DropdownMenuItem(value: y, child: Text('$y'))).toList(),
                onChanged: (v) {
                  if (v != null) ref.read(_selectedYearProvider.notifier).state = v;
                },
              ),
            ),
          ),
        ],
      ),
      body: payslipsAsync.when(
        loading: () => _PayslipsSkeleton(),
        error: (e, _) => _ErrorView(onRetry: () => ref.invalidate(payslipsProvider(year))),
        data: (payslips) => payslips.isEmpty
            ? _EmptyView(year: year)
            : _PayslipList(payslips: payslips),
      ),
    );
  }
}

// ─── Lista agrupada por mês ──────────────────────────────────────────────────

class _PayslipList extends StatelessWidget {
  final List<PayslipModel> payslips;

  const _PayslipList({required this.payslips});

  static const _monthNames = [
    '', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
    'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
  ];

  @override
  Widget build(BuildContext context) {
    // Agrupar por mês
    final grouped = <int, List<PayslipModel>>{};
    for (final p in payslips) {
      grouped.putIfAbsent(p.referenceMonth, () => []).add(p);
    }
    final months = grouped.keys.toList()..sort((a, b) => b.compareTo(a));

    return ListView.builder(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
      itemCount: months.length,
      itemBuilder: (ctx, i) {
        final month = months[i];
        final items = grouped[month]!;
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: EdgeInsets.only(bottom: 10, top: i == 0 ? 0 : 16),
              child: Text(
                _monthNames[month],
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                  color: AppColors.textSecondary,
                  letterSpacing: 0.3,
                ),
              ),
            ),
            ...items.map((p) => _PayslipCard(payslip: p)),
          ],
        );
      },
    );
  }
}

// ─── Card de holerite ────────────────────────────────────────────────────────

class _PayslipCard extends StatelessWidget {
  final PayslipModel payslip;

  const _PayslipCard({required this.payslip});

  Future<void> _open(BuildContext context) async {
    final uri = Uri.parse(payslip.fileUrl);
    try {
      if (await canLaunchUrl(uri)) {
        await launchUrl(uri, mode: LaunchMode.externalApplication);
      } else {
        throw Exception('Não foi possível abrir o arquivo.');
      }
    } catch (_) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Não foi possível abrir o holerite.'),
            backgroundColor: AppColors.error,
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.divider),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: InkWell(
        onTap: () => _open(context),
        borderRadius: BorderRadius.circular(14),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
          child: Row(
            children: [
              // Ícone PDF
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: const Color(0xFFEF4444).withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Icon(
                  Icons.picture_as_pdf_rounded,
                  color: Color(0xFFEF4444),
                  size: 22,
                ),
              ),
              const SizedBox(width: 14),
              // Informações
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      payslip.displayTitle,
                      style: const TextStyle(
                        fontWeight: FontWeight.w600,
                        fontSize: 14,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Row(
                      children: [
                        Text(
                          payslip.referenceLabel,
                          style: const TextStyle(
                            fontSize: 12,
                            color: AppColors.textSecondary,
                          ),
                        ),
                        if (payslip.fileSizeLabel?.isNotEmpty == true) ...[
                          const Text(
                            ' · ',
                            style: TextStyle(color: AppColors.textHint, fontSize: 12),
                          ),
                          Text(
                            payslip.fileSizeLabel!,
                            style: const TextStyle(
                              fontSize: 12,
                              color: AppColors.textHint,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ],
                ),
              ),
              // Botão download
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.08),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(
                  Icons.download_rounded,
                  color: AppColors.primary,
                  size: 18,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ─── Estados vazios / erro ───────────────────────────────────────────────────

class _EmptyView extends StatelessWidget {
  final int year;
  const _EmptyView({required this.year});

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
              decoration: BoxDecoration(
                color: AppColors.surfaceVariant,
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.receipt_long_rounded,
                size: 48,
                color: AppColors.textHint,
              ),
            ),
            const SizedBox(height: 20),
            Text(
              'Nenhum holerite em $year',
              style: const TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w600,
                color: AppColors.textPrimary,
              ),
            ),
            const SizedBox(height: 8),
            const Text(
              'Os holerites disponibilizados pela sua empresa aparecerão aqui.',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 13, color: AppColors.textSecondary, height: 1.5),
            ),
          ],
        ),
      ),
    );
  }
}

class _ErrorView extends StatelessWidget {
  final VoidCallback onRetry;
  const _ErrorView({required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.cloud_off_rounded, size: 48, color: AppColors.textHint),
          const SizedBox(height: 16),
          const Text('Não foi possível carregar os holerites.',
              style: TextStyle(color: AppColors.textSecondary)),
          const SizedBox(height: 12),
          ElevatedButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh, size: 18),
            label: const Text('Tentar novamente'),
          ),
        ],
      ),
    );
  }
}

class _PayslipsSkeleton extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: 8,
      separatorBuilder: (_, __) => const SizedBox(height: 10),
      itemBuilder: (_, __) => Padding(
        padding: const EdgeInsets.symmetric(horizontal: 4),
        child: Row(
          children: [
            SkeletonShimmer(width: 44, height: 44, borderRadius: 12),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SkeletonShimmer(width: double.infinity, height: 13, borderRadius: 6),
                  const SizedBox(height: 6),
                  SkeletonShimmer(width: 100, height: 11, borderRadius: 5),
                ],
              ),
            ),
            const SizedBox(width: 12),
            SkeletonShimmer(width: 36, height: 36, borderRadius: 10),
          ],
        ),
      ),
    );
  }
}
