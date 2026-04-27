import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'auth_provider.dart';
import '../../core/theme/app_theme.dart';
import '../../data/datasources/auth_datasource.dart';
import '../../data/models/user_model.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen>
    with SingleTickerProviderStateMixin {
  final _formKey = GlobalKey<FormState>();
  final _emailCtrl = TextEditingController();
  final _passwordCtrl = TextEditingController();
  final _emailFocus = FocusNode();
  final _passwordFocus = FocusNode();

  bool _obscurePassword = true;
  bool _rememberMe = false;

  // Credenciais salvas — apenas o nome é exibido, email/senha ficam ocultos
  String? _savedName;
  String? _savedEmail;
  String? _savedPassword;
  bool _hasSavedSession = false;

  late final AnimationController _animCtrl;
  late final Animation<double> _fadeAnim;
  late final Animation<Offset> _slideAnim;

  @override
  void initState() {
    super.initState();
    _animCtrl = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 600),
    );
    _fadeAnim = CurvedAnimation(parent: _animCtrl, curve: Curves.easeOut);
    _slideAnim = Tween<Offset>(
      begin: const Offset(0, 0.08),
      end: Offset.zero,
    ).animate(CurvedAnimation(parent: _animCtrl, curve: Curves.easeOut));

    _animCtrl.forward();
    _loadSavedCredentials();
  }

  Future<void> _loadSavedCredentials() async {
    final creds = await ref.read(authProvider.notifier).getSavedCredentials();
    if (!mounted) return;
    if (creds != null) {
      // Guardar internamente mas NÃO preencher os campos visíveis
      _savedEmail    = creds['email'];
      _savedPassword = creds['password'];
      _savedName     = creds['name'];
      setState(() {
        _rememberMe       = true;
        _hasSavedSession  = true;
      });
    }
  }

  /// Login automático usando as credenciais salvas (tocando no card).
  Future<void> _loginWithSaved() async {
    if (_savedEmail == null || _savedPassword == null) return;
    FocusScope.of(context).unfocus();
    final result = await ref.read(authProvider.notifier).loginFull(
          _savedEmail!,
          _savedPassword!,
          rememberMe: true,
        );
    if (!mounted || result.isEmpty) return;
    final user = result['user'] as UserModel;
    final faceEnrolled = result['face_enrolled'] as bool? ?? false;
    if (user.role == 'totem') {
      context.go('/totem');
    } else if (!faceEnrolled && user.isFuncionario) {
      context.go('/face-enroll');
    } else {
      context.go('/home');
    }
  }

  /// Descarta a sessão salva e mostra o formulário normal.
  void _clearSavedSession() {
    ref.read(authProvider.notifier).getSavedCredentials().then((_) {
      ref.read(authDatasourceProvider).clearCredentials();
    });
    setState(() {
      _hasSavedSession  = false;
      _savedEmail       = null;
      _savedPassword    = null;
      _savedName        = null;
      _rememberMe       = false;
    });
  }

  @override
  void dispose() {
    _emailCtrl.dispose();
    _passwordCtrl.dispose();
    _emailFocus.dispose();
    _passwordFocus.dispose();
    _animCtrl.dispose();
    super.dispose();
  }

  Future<void> _login() async {
    if (!_formKey.currentState!.validate()) return;
    FocusScope.of(context).unfocus();

    final result = await ref.read(authProvider.notifier).loginFull(
          _emailCtrl.text.trim(),
          _passwordCtrl.text,
          rememberMe: _rememberMe,
        );

    if (!mounted) return;
    if (result.isEmpty) return;

    final user = result['user'] as UserModel;
    final faceEnrolled = result['face_enrolled'] as bool? ?? false;

    if (user.role == 'totem') {
      context.go('/totem');
    } else if (!faceEnrolled && user.isFuncionario) {
      context.go('/face-enroll');
    } else {
      context.go('/home');
    }
  }

  @override
  Widget build(BuildContext context) {
    final authState = ref.watch(authProvider);
    final isLoading = authState.isLoading;
    final size = MediaQuery.of(context).size;
    final scheme = Theme.of(context).colorScheme;
    final fieldTextStyle = TextStyle(color: scheme.onSurface);
    final isDarkField =
        Theme.of(context).brightness == Brightness.dark;
    // Em fundo escuro, ícones um pouco mais claros que o hint padrão
    final iconColor =
        isDarkField ? const Color(0xFFCBD5E1) : AppColors.textHint;

    return Scaffold(
      backgroundColor: AppColors.primary,
      resizeToAvoidBottomInset: true,
      body: Stack(
        children: [
          // ── Fundo gradiente com formas decorativas ──────────────────────
          Positioned.fill(
            child: DecoratedBox(
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [AppColors.primaryDark, AppColors.primary, AppColors.primaryLight],
                  stops: [0.0, 0.5, 1.0],
                ),
              ),
            ),
          ),
          // Círculo decorativo superior
          Positioned(
            top: -size.width * 0.3,
            right: -size.width * 0.2,
            child: Container(
              width: size.width * 0.75,
              height: size.width * 0.75,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.white.withValues(alpha: 0.06),
              ),
            ),
          ),
          // Círculo decorativo inferior esquerdo
          Positioned(
            bottom: size.height * 0.3,
            left: -size.width * 0.15,
            child: Container(
              width: size.width * 0.45,
              height: size.width * 0.45,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.white.withValues(alpha: 0.04),
              ),
            ),
          ),

          // ── Conteúdo ────────────────────────────────────────────────────
          SafeArea(
            child: Column(
              children: [
                // ── Header ─────────────────────────────────────────────────
                SizedBox(
                  height: size.height * 0.30,
                  child: FadeTransition(
                    opacity: _fadeAnim,
                    child: Center(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          // Logo oficial RM Ponto
                          Image.asset(
                            'assets/images/logo_login.png',
                            width: size.width * 0.55,
                            height: size.width * 0.55,
                            fit: BoxFit.contain,
                            // Fallback se a imagem não carregar
                            errorBuilder: (_, __, ___) => Container(
                              width: 76,
                              height: 76,
                              decoration: BoxDecoration(
                                color: Colors.white.withValues(alpha: 0.15),
                                shape: BoxShape.circle,
                              ),
                              child: const Icon(Icons.access_time, size: 44, color: Colors.white),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),

                // ── Painel de formulário ────────────────────────────────────
                Expanded(
                  child: SlideTransition(
                    position: _slideAnim,
                    child: FadeTransition(
                      opacity: _fadeAnim,
                      child: Container(
                        decoration: const BoxDecoration(
                          color: AppColors.background,
                          borderRadius: BorderRadius.vertical(top: Radius.circular(32)),
                        ),
                        child: SingleChildScrollView(
                          padding: EdgeInsets.fromLTRB(
                            28,
                            32,
                            28,
                            MediaQuery.of(context).viewInsets.bottom + 28,
                          ),
                          child: Form(
                            key: _formKey,
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                // Título
                                const Text(
                                  'Bem-vindo!',
                                  style: TextStyle(
                                    fontSize: 26,
                                    fontWeight: FontWeight.bold,
                                    color: AppColors.textPrimary,
                                  ),
                                ),
                                const SizedBox(height: 4),
                                const Text(
                                  'Faça login para registrar seu ponto',
                                  style: TextStyle(
                                    color: AppColors.textSecondary,
                                    fontSize: 14,
                                  ),
                                ),
                                const SizedBox(height: 28),

                                // ── Card de sessão salva ──────────────────────
                                if (_hasSavedSession) ...[
                                  _SavedSessionCard(
                                    name: _savedName,
                                    isLoading: isLoading,
                                    onTap: _loginWithSaved,
                                    onClear: _clearSavedSession,
                                  ),
                                  const SizedBox(height: 16),
                                  Center(
                                    child: TextButton(
                                      onPressed: isLoading
                                          ? null
                                          : () => setState(
                                              () => _hasSavedSession = false),
                                      child: const Text(
                                        'Usar outra conta',
                                        style: TextStyle(
                                            color: AppColors.textSecondary,
                                            fontSize: 13),
                                      ),
                                    ),
                                  ),
                                  if (authState.errorMessage != null) ...[
                                    const SizedBox(height: 12),
                                    _ErrorBanner(
                                        message: authState.errorMessage!),
                                  ],
                                ]

                                // ── Formulário normal ─────────────────────────
                                else ...[

                                // ── Campo email ──────────────────────────────
                                TextFormField(
                                  controller: _emailCtrl,
                                  focusNode: _emailFocus,
                                  keyboardType: TextInputType.emailAddress,
                                  textInputAction: TextInputAction.next,
                                  enabled: !isLoading,
                                  style: fieldTextStyle,
                                  onFieldSubmitted: (_) =>
                                      FocusScope.of(context).requestFocus(_passwordFocus),
                                  decoration: InputDecoration(
                                    labelText: 'E-mail',
                                    hintText: 'seu@email.com',
                                    prefixIconColor: iconColor,
                                    suffixIconColor: iconColor,
                                    prefixIcon: const Icon(Icons.email_outlined),
                                    suffixIcon: _emailCtrl.text.isNotEmpty
                                        ? IconButton(
                                            icon: const Icon(Icons.clear, size: 18),
                                            onPressed: () {
                                              _emailCtrl.clear();
                                              setState(() {});
                                            },
                                          )
                                        : null,
                                  ),
                                  onChanged: (_) => setState(() {}),
                                  validator: (v) {
                                    if (v == null || v.isEmpty) return 'Informe o e-mail';
                                    if (!v.contains('@')) return 'E-mail inválido';
                                    return null;
                                  },
                                ),
                                const SizedBox(height: 16),

                                // ── Campo senha ──────────────────────────────
                                TextFormField(
                                  controller: _passwordCtrl,
                                  focusNode: _passwordFocus,
                                  obscureText: _obscurePassword,
                                  textInputAction: TextInputAction.done,
                                  enabled: !isLoading,
                                  style: fieldTextStyle,
                                  onFieldSubmitted: (_) => _login(),
                                  decoration: InputDecoration(
                                    labelText: 'Senha',
                                    hintText: '••••••••',
                                    prefixIconColor: iconColor,
                                    suffixIconColor: iconColor,
                                    prefixIcon: const Icon(Icons.lock_outline),
                                    suffixIcon: IconButton(
                                      icon: Icon(
                                        _obscurePassword
                                            ? Icons.visibility_outlined
                                            : Icons.visibility_off_outlined,
                                        size: 20,
                                      ),
                                      onPressed: () => setState(
                                          () => _obscurePassword = !_obscurePassword),
                                    ),
                                  ),
                                  validator: (v) {
                                    if (v == null || v.isEmpty) return 'Informe a senha';
                                    if (v.length < 6) return 'Mínimo 6 caracteres';
                                    return null;
                                  },
                                ),

                                const SizedBox(height: 12),

                                // ── Lembrar de mim ───────────────────────────
                                InkWell(
                                  onTap: isLoading
                                      ? null
                                      : () => setState(() => _rememberMe = !_rememberMe),
                                  borderRadius: BorderRadius.circular(8),
                                  child: Padding(
                                    padding: const EdgeInsets.symmetric(
                                        vertical: 4, horizontal: 2),
                                    child: Row(
                                      children: [
                                        AnimatedContainer(
                                          duration: const Duration(milliseconds: 200),
                                          width: 22,
                                          height: 22,
                                          decoration: BoxDecoration(
                                            color: _rememberMe
                                                ? AppColors.primary
                                                : Colors.transparent,
                                            border: Border.all(
                                              color: _rememberMe
                                                  ? AppColors.primary
                                                  : AppColors.textHint,
                                              width: 2,
                                            ),
                                            borderRadius: BorderRadius.circular(5),
                                          ),
                                          child: _rememberMe
                                              ? const Icon(Icons.check,
                                                  size: 14, color: Colors.white)
                                              : null,
                                        ),
                                        const SizedBox(width: 10),
                                        const Text(
                                          'Lembrar de mim',
                                          style: TextStyle(
                                            color: AppColors.textSecondary,
                                            fontSize: 14,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ),

                                // ── Mensagem de erro ─────────────────────────
                                if (authState.errorMessage != null) ...[
                                  const SizedBox(height: 16),
                                  AnimatedContainer(
                                    duration: const Duration(milliseconds: 300),
                                    padding: const EdgeInsets.all(14),
                                    decoration: BoxDecoration(
                                      color: AppColors.error.withValues(alpha: 0.07),
                                      borderRadius: BorderRadius.circular(12),
                                      border: Border.all(
                                          color: AppColors.error.withValues(alpha: 0.25)),
                                    ),
                                    child: Row(
                                      children: [
                                        const Icon(Icons.error_outline,
                                            color: AppColors.error, size: 18),
                                        const SizedBox(width: 10),
                                        Expanded(
                                          child: Text(
                                            authState.errorMessage!,
                                            style: const TextStyle(
                                                color: AppColors.error, fontSize: 13),
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ],

                                const SizedBox(height: 28),

                                // ── Botão Entrar ─────────────────────────────
                                SizedBox(
                                  width: double.infinity,
                                  child: ElevatedButton(
                                    onPressed: isLoading ? null : _login,
                                    style: ElevatedButton.styleFrom(
                                      backgroundColor: AppColors.primary,
                                      disabledBackgroundColor:
                                          AppColors.primary.withValues(alpha: 0.6),
                                      minimumSize: const Size.fromHeight(54),
                                      shape: RoundedRectangleBorder(
                                          borderRadius: BorderRadius.circular(14)),
                                      elevation: isLoading ? 0 : 3,
                                      shadowColor:
                                          AppColors.primary.withValues(alpha: 0.4),
                                    ),
                                    child: AnimatedSwitcher(
                                      duration: const Duration(milliseconds: 200),
                                      child: isLoading
                                          ? const SizedBox(
                                              key: ValueKey('loading'),
                                              height: 22,
                                              width: 22,
                                              child: CircularProgressIndicator(
                                                strokeWidth: 2.5,
                                                color: Colors.white,
                                              ),
                                            )
                                          : const Row(
                                              key: ValueKey('label'),
                                              mainAxisAlignment:
                                                  MainAxisAlignment.center,
                                              children: [
                                                Icon(Icons.login,
                                                    color: Colors.white, size: 20),
                                                SizedBox(width: 8),
                                                Text(
                                                  'Entrar',
                                                  style: TextStyle(
                                                    fontSize: 16,
                                                    fontWeight: FontWeight.bold,
                                                    color: Colors.white,
                                                  ),
                                                ),
                                              ],
                                            ),
                                    ),
                                  ),
                                ),

                                const SizedBox(height: 20),

                                // ── Rodapé ───────────────────────────────────
                                Center(
                                  child: Text(
                                    'Ponto Digital © ${DateTime.now().year}',
                                    style: const TextStyle(
                                      color: AppColors.textHint,
                                      fontSize: 12,
                                    ),
                                  ),
                                ),

                                ], // fim else formulário normal
                              ],
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Card "Continuar como [Nome]" — não exibe email nem senha
// ─────────────────────────────────────────────────────────────────────────────
class _SavedSessionCard extends StatelessWidget {
  final String? name;
  final bool isLoading;
  final VoidCallback onTap;
  final VoidCallback onClear;

  const _SavedSessionCard({
    required this.name,
    required this.isLoading,
    required this.onTap,
    required this.onClear,
  });

  /// Abrevia para "Nome S." (primeiro nome + inicial do apelido)
  String _abbreviate(String? fullName) {
    if (fullName == null || fullName.isEmpty) return 'Utilizador';
    final parts = fullName.trim().split(RegExp(r'\s+'));
    if (parts.length == 1) return parts.first;
    return '${parts.first} ${parts.last[0].toUpperCase()}.';
  }

  @override
  Widget build(BuildContext context) {
    final abbreviated = _abbreviate(name);
    final initial = abbreviated.isNotEmpty ? abbreviated[0].toUpperCase() : '?';

    return GestureDetector(
      onTap: isLoading ? null : onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        decoration: BoxDecoration(
          color: isLoading
              ? AppColors.primary.withValues(alpha: 0.04)
              : AppColors.surface,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: AppColors.primary.withValues(alpha: 0.35),
            width: 1.5,
          ),
          boxShadow: [
            BoxShadow(
              color: AppColors.primary.withValues(alpha: 0.08),
              blurRadius: 12,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Row(
          children: [
            // Avatar com inicial
            Container(
              width: 46,
              height: 46,
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [AppColors.primary, AppColors.primaryLight],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                shape: BoxShape.circle,
              ),
              child: Center(
                child: Text(
                  initial,
                  style: const TextStyle(
                      color: Colors.white,
                      fontSize: 20,
                      fontWeight: FontWeight.bold),
                ),
              ),
            ),
            const SizedBox(width: 14),

            // Nome abreviado
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Continuar como',
                    style: TextStyle(
                        fontSize: 11, color: AppColors.textSecondary),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    abbreviated,
                    style: const TextStyle(
                      fontSize: 17,
                      fontWeight: FontWeight.bold,
                      color: AppColors.textPrimary,
                    ),
                  ),
                ],
              ),
            ),

            // Ícone ou spinner
            if (isLoading)
              const SizedBox(
                width: 22,
                height: 22,
                child: CircularProgressIndicator(
                    strokeWidth: 2.5, color: AppColors.primary),
              )
            else ...[
              const Icon(Icons.arrow_forward_ios,
                  size: 16, color: AppColors.primary),
              const SizedBox(width: 8),
              // Botão X para limpar
              GestureDetector(
                onTap: onClear,
                child: Container(
                  width: 28,
                  height: 28,
                  decoration: BoxDecoration(
                    color: AppColors.surfaceVariant,
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(Icons.close,
                      size: 14, color: AppColors.textSecondary),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _ErrorBanner extends StatelessWidget {
  final String message;
  const _ErrorBanner({required this.message});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.error.withValues(alpha: 0.07),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.error.withValues(alpha: 0.25)),
      ),
      child: Row(
        children: [
          const Icon(Icons.error_outline, color: AppColors.error, size: 18),
          const SizedBox(width: 10),
          Expanded(
            child: Text(message,
                style:
                    const TextStyle(color: AppColors.error, fontSize: 13)),
          ),
        ],
      ),
    );
  }
}
