import 'package:flutter/material.dart';

/// Caixa animada de shimmer para estados de carregamento.
/// Usa [TweenAnimationBuilder] para não precisar de [TickerProvider] externo.
class SkeletonBox extends StatelessWidget {
  final double? width;
  final double? height;
  final double borderRadius;

  const SkeletonBox({
    super.key,
    this.width,
    this.height,
    this.borderRadius = 12,
  });

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final base = isDark ? const Color(0xFF334155) : const Color(0xFFE8EDF5);
    final mid = isDark ? const Color(0xFF475569) : const Color(0xFFF5F7FA);

    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0.0, end: 1.0),
      duration: const Duration(milliseconds: 1200),
      curve: Curves.easeInOut,
      builder: (context, value, child) {
        return Container(
          width: width,
          height: height,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(borderRadius),
            gradient: LinearGradient(
              begin: Alignment.centerLeft,
              end: Alignment.centerRight,
              colors: [
                base,
                Color.lerp(
                  base,
                  mid,
                  (value <= 0.5 ? value * 2 : (1.0 - value) * 2)
                      .clamp(0.0, 1.0),
                )!,
                base,
              ],
              stops: const [0.0, 0.5, 1.0],
            ),
          ),
        );
      },
      // Loop infinito — reconstrói a cada ciclo
      onEnd: null,
      child: const SizedBox.shrink(),
    );
  }
}

/// Versão com loop infinito usando AnimationController.
class SkeletonShimmer extends StatefulWidget {
  final double? width;
  final double height;
  final double borderRadius;

  const SkeletonShimmer({
    super.key,
    this.width,
    this.height = 16,
    this.borderRadius = 8,
  });

  @override
  State<SkeletonShimmer> createState() => _SkeletonShimmerState();
}

class _SkeletonShimmerState extends State<SkeletonShimmer>
    with SingleTickerProviderStateMixin {
  late AnimationController _controller;
  late Animation<double> _animation;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1300),
    )..repeat(reverse: true);
    _animation = CurvedAnimation(parent: _controller, curve: Curves.easeInOut);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final edge = isDark ? const Color(0xFF334155) : const Color(0xFFE4E9F2);
    final peak = isDark ? const Color(0xFF475569) : const Color(0xFFF3F6FB);

    return AnimatedBuilder(
      animation: _animation,
      builder: (_, __) => Container(
        width: widget.width,
        height: widget.height,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(widget.borderRadius),
          gradient: LinearGradient(
            begin: Alignment.centerLeft,
            end: Alignment.centerRight,
            colors: [
              edge,
              Color.lerp(edge, peak, _animation.value)!,
              edge,
            ],
            stops: const [0.0, 0.5, 1.0],
          ),
        ),
      ),
    );
  }
}

// ── Skeletons compostos pré-fabricados ──────────────────────────────────────

/// Skeleton de um card genérico com altura variável.
class SkeletonCard extends StatelessWidget {
  final double height;
  final double borderRadius;

  const SkeletonCard({super.key, this.height = 80, this.borderRadius = 16});

  @override
  Widget build(BuildContext context) {
    return SkeletonShimmer(
      width: double.infinity,
      height: height,
      borderRadius: borderRadius,
    );
  }
}

/// Skeleton de uma lista de itens — imitando tiles com ícone + texto.
class SkeletonListTile extends StatelessWidget {
  const SkeletonListTile({super.key});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      child: Row(
        children: [
          SkeletonShimmer(width: 44, height: 44, borderRadius: 12),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SkeletonShimmer(width: double.infinity, height: 14, borderRadius: 7),
                const SizedBox(height: 6),
                SkeletonShimmer(width: 160, height: 11, borderRadius: 6),
              ],
            ),
          ),
          const SizedBox(width: 12),
          SkeletonShimmer(width: 40, height: 11, borderRadius: 6),
        ],
      ),
    );
  }
}

/// Skeleton para a seção de ponto (botão de bater ponto).
class SkeletonPunchButton extends StatelessWidget {
  const SkeletonPunchButton({super.key});

  @override
  Widget build(BuildContext context) {
    return const SkeletonCard(height: 72, borderRadius: 20);
  }
}
