import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../auth/auth_provider.dart';

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
          if (emp != null) ...[
            const SizedBox(height: 24),
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
              subtitle: '${emp.weeklyHours} h',
            ),
          ],
          const SizedBox(height: 32),
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
