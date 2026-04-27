import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../auth/auth_provider.dart';
import '../balance/hour_bank_provider.dart';
import '../../data/models/hour_bank_request_model.dart';

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authProvider).user;
    if (user == null) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    final emp = user.employee;
    final company = emp?.company;

    final c = Theme.of(context).colorScheme;
    final balanceAsync = ref.watch(hourBankBalanceProvider);
    final requestsAsync = ref.watch(hourBankRequestsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Perfil')),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          Center(
            child: CircleAvatar(
              radius: 48,
              backgroundColor: c.primary.withValues(alpha: 0.2),
              child: Text(
                user.firstName.isNotEmpty ? user.firstName[0].toUpperCase() : '?',
                style: TextStyle(fontSize: 40, color: c.primary, fontWeight: FontWeight.bold),
              ),
            ),
          ),
          const SizedBox(height: 16),
          Text(
            user.name,
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: c.onSurface),
          ),
          const SizedBox(height: 4),
          Text(
            user.email,
            textAlign: TextAlign.center,
            style: TextStyle(color: c.onSurfaceVariant, fontSize: 14),
          ),
          const SizedBox(height: 8),
          Center(
            child: Chip(
              label: Text(_roleLabel(user.role)),
              backgroundColor: c.primaryContainer.withValues(alpha: 0.4),
            ),
          ),

          // ── Saldo banco de horas ─────────────────────────────────────
          if (emp != null) ...[
            const SizedBox(height: 20),
            balanceAsync.when(
              loading: () => const SizedBox(height: 60, child: Center(child: CircularProgressIndicator())),
              error: (_, __) => const SizedBox.shrink(),
              data: (balance) => _BankBalanceCard(balance: balance),
            ),
          ],

          // ── Próxima folga aprovada ────────────────────────────────────
          if (emp != null)
            requestsAsync.when(
              loading: () => const SizedBox.shrink(),
              error: (_, __) => const SizedBox.shrink(),
              data: (requests) {
                final nextLeave = requests
                    .where((r) => r.status == 'aprovado')
                    .where((r) {
                      try {
                        final d = DateTime.parse(r.requestedDate);
                        return d.isAfter(DateTime.now().subtract(const Duration(days: 1)));
                      } catch (_) { return false; }
                    })
                    .toList()
                  ..sort((a, b) => a.requestedDate.compareTo(b.requestedDate));
                if (nextLeave.isEmpty) return const SizedBox.shrink();
                return Padding(
                  padding: const EdgeInsets.only(top: 12),
                  child: _NextLeaveCard(leave: nextLeave.first),
                );
              },
            ),

          if (emp != null) ...[
            const SizedBox(height: 20),
            Text('Dados profissionais', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 15, color: c.onSurface)),
            const SizedBox(height: 12),
            _InfoTile(
              c: c,
              icon: Icons.work_outline,
              title: 'Cargo',
              subtitle: emp.cargo,
            ),
            if (emp.department != null)
              _InfoTile(
                c: c,
                icon: Icons.apartment,
                title: 'Departamento',
                subtitle: emp.department!,
              ),
            if (company != null)
              _InfoTile(
                c: c,
                icon: Icons.business,
                title: 'Empresa',
                subtitle: company.name,
              ),
            _InfoTile(c: c, icon: Icons.badge_outlined, title: 'CPF', subtitle: emp.cpf),
            _InfoTile(
              c: c,
              icon: Icons.schedule,
              title: 'Jornada semanal',
              subtitle: '${emp.weeklyHours} h/semana',
            ),
          ],
          const SizedBox(height: 32),
          ListTile(
            leading: Icon(Icons.bar_chart_rounded, color: c.primary),
            title: const Text('Banco de Horas'),
            subtitle: const Text('Saldo, movimentos e solicitações'),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => context.pushNamed('balance'),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            tileColor: c.surfaceContainerHighest,
          ),
          const SizedBox(height: 8),
          ListTile(
            leading: Icon(Icons.settings_outlined, color: c.primary),
            title: const Text('Configurações'),
            subtitle: const Text('Tema, biometria e notificações'),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => context.push('/home/settings'),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            tileColor: c.surfaceContainerHighest,
          ),
          const SizedBox(height: 8),
          ListTile(
            leading: Icon(Icons.edit_note, color: c.primary),
            title: const Text('Solicitações de correção'),
            subtitle: const Text('Acompanhe pedidos de ajuste de ponto'),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => context.pushNamed('edit-requests'),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            tileColor: c.surfaceContainerHighest,
          ),
        ],
      ),
    );
  }

  String _roleLabel(String role) {
    return switch (role) {
      'admin' => 'Administrador',
      'gestor' => 'Gestor de RH',
      'funcionario' => 'Colaborador',
      _ => role,
    };
  }
}

class _BankBalanceCard extends StatelessWidget {
  final HourBankBalanceModel balance;
  const _BankBalanceCard({required this.balance});

  @override
  Widget build(BuildContext context) {
    final isPos = balance.isPositive;
    final color = isPos ? const Color(0xFF059669) : const Color(0xFFDC2626);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.07),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: color.withValues(alpha: 0.3)),
      ),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.15),
              shape: BoxShape.circle,
            ),
            child: Icon(
              isPos ? Icons.trending_up : Icons.trending_down,
              color: color,
              size: 22,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Banco de horas',
                    style: TextStyle(fontSize: 12, color: Color(0xFF64748B))),
                Text(
                  balance.formatted,
                  style: TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                      color: color),
                ),
              ],
            ),
          ),
          Text(
            isPos ? 'Crédito' : 'Débito',
            style: TextStyle(fontSize: 12, color: color, fontWeight: FontWeight.w600),
          ),
        ],
      ),
    );
  }
}

class _NextLeaveCard extends StatelessWidget {
  final HourBankRequestModel leave;
  const _NextLeaveCard({required this.leave});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: BoxDecoration(
        color: const Color(0xFFF0FDF4),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFF86EFAC)),
      ),
      child: Row(
        children: [
          const Icon(Icons.event_available_outlined, color: Color(0xFF059669), size: 22),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Próxima folga aprovada',
                    style: TextStyle(fontSize: 12, color: Color(0xFF64748B))),
                Text(
                  leave.dateFormatted,
                  style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.bold,
                      color: Color(0xFF059669)),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _InfoTile extends StatelessWidget {
  final ColorScheme c;
  final IconData icon;
  final String title;
  final String subtitle;

  const _InfoTile({
    required this.c,
    required this.icon,
    required this.title,
    required this.subtitle,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 20, color: c.onSurfaceVariant),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: TextStyle(fontSize: 12, color: c.onSurfaceVariant)),
                Text(
                  subtitle,
                  style: TextStyle(fontSize: 15, color: c.onSurface),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
