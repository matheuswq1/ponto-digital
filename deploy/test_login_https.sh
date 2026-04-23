#!/bin/bash
echo "=== TESTAR LOGIN VIA HTTPS ==="
curl -s -X POST https://ponto.approsamistica.com/api/v1/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"matheus83wq@gmail.com","password":"senha123","device_name":"test"}'
echo ""

echo "=== TAMBÉM CRIAR EMPLOYEE PARA O UTILIZADOR (se necessário) ==="
cd /home/ponto/htdocs/ponto.approsamistica.com
sudo -u ponto php artisan tinker --execute="
\$u = App\Models\User::where('email','matheus83wq@gmail.com')->first();
if (\$u && !\$u->employee) {
    \$company = App\Models\Company::first();
    if (\$company) {
        App\Models\Employee::create([
            'user_id'        => \$u->id,
            'company_id'     => \$company->id,
            'cpf'            => '000.000.000-00',
            'cargo'          => 'Administrador',
            'admission_date' => '2024-01-01',
            'contract_type'  => 'clt',
            'weekly_hours'   => 44,
            'active'         => true,
        ]);
        echo 'Employee criado';
    } else { echo 'Sem empresa - crie uma primeiro'; }
} else { echo 'Employee já existe ou user não encontrado'; }
"
