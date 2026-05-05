import 'package:flutter/material.dart';

/// Cores semânticas a partir do [Theme] actual — usar em vez de [AppColors]
/// fixos para respeitar claro / escuro / sistema.
extension AdaptiveColors on BuildContext {
  ThemeData get _t => Theme.of(this);

  Color get appBackground => _t.scaffoldBackgroundColor;

  Color get appSurface => _t.colorScheme.surface;

  Color get appSurfaceVariant => Theme.of(this).brightness == Brightness.dark
      ? const Color(0xFF334155)
      : const Color(0xFFF1F5F9);

  Color get appBorder => _t.colorScheme.outline.withValues(alpha: 0.45);

  Color get appDivider => _t.dividerTheme.color ?? _t.colorScheme.outlineVariant;

  Color get appTextPrimary => _t.colorScheme.onSurface;

  Color get appTextSecondary => _t.colorScheme.onSurfaceVariant;

  Color get appNavBarBg => _t.colorScheme.surface;

  Color get appShadow => _t.brightness == Brightness.dark
      ? Colors.black.withValues(alpha: 0.45)
      : Colors.black.withValues(alpha: 0.08);

  Color get badgeBorderOnNav => _t.colorScheme.surface;
}
