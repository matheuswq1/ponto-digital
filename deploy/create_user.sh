#!/bin/bash
cd /home/ponto/htdocs/ponto.approsamistica.com

# Criar utilizador matheus83wq@gmail.com se não existir
sudo -u ponto php artisan tinker --execute="
\$email = 'matheus83wq@gmail.com';
if (!App\Models\User::where('email', \$email)->exists()) {
    \$u = App\Models\User::create([
        'name'     => 'Matheus',
        'email'    => \$email,
        'password' => bcrypt('senha123'),
        'role'     => 'admin',
        'active'   => true,
    ]);
    \$u->assignRole('admin');
    echo 'Utilizador criado: ' . \$u->email;
} else {
    \$u = App\Models\User::where('email', \$email)->first();
    \$u->update(['password' => bcrypt('senha123'), 'active' => true]);
    echo 'Senha atualizada para: ' . \$u->email;
}
"

echo ""
echo "=== TESTAR LOGIN ==="
curl -s -X POST http://127.0.0.1:8080/api/v1/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"matheus83wq@gmail.com","password":"senha123","device_name":"test"}' | python3 -m json.tool 2>/dev/null || echo "(resposta acima)"
