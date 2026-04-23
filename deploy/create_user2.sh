#!/bin/bash
cd /home/ponto/htdocs/ponto.approsamistica.com

sudo -u ponto php artisan tinker --execute="
\$email = 'matheus83wq@gmail.com';
\$u = App\Models\User::updateOrCreate(
    ['email' => \$email],
    [
        'name'     => 'Matheus',
        'password' => bcrypt('senha123'),
        'role'     => 'admin',
        'active'   => true,
    ]
);
echo 'OK: ' . \$u->email . ' id=' . \$u->id;
"

echo ""
echo "=== TESTAR LOGIN ==="
curl -s -X POST http://127.0.0.1:8080/api/v1/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"matheus83wq@gmail.com","password":"senha123","device_name":"test"}'
echo ""
