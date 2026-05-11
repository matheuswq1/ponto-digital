import 'package:flutter/material.dart';

import '../../core/theme/app_theme.dart';

/// Etiquetas vindas da API (`day_calendar.labels_pt`): falta, feriado, folga, fim de semana, etc.
class DayCalendarLabelChips extends StatelessWidget {
  const DayCalendarLabelChips({
    super.key,
    required this.labels,
    this.spacing = 4,
    this.runSpacing = 4,
    this.fontSize = 10,
    this.padding = const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
  });

  final List<String> labels;
  final double spacing;
  final double runSpacing;
  final double fontSize;
  final EdgeInsetsGeometry padding;

  static Color colorForLabel(String label) {
    return switch (label) {
      'Falta' => AppColors.error,
      'Feriado' => AppColors.info,
      'Folga' => AppColors.success,
      'Afastamento' => AppColors.warning,
      'Sábado' => AppColors.textSecondary,
      'Domingo' => AppColors.textSecondary,
      _ => AppColors.textSecondary,
    };
  }

  @override
  Widget build(BuildContext context) {
    if (labels.isEmpty) return const SizedBox.shrink();
    return Wrap(
      spacing: spacing,
      runSpacing: runSpacing,
      children: labels.map((l) {
        final color = colorForLabel(l);
        return Container(
          padding: padding,
          decoration: BoxDecoration(
            color: color.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(4),
          ),
          child: Text(
            l,
            style: TextStyle(color: color, fontSize: fontSize),
          ),
        );
      }).toList(),
    );
  }
}
