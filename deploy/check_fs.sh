#!/bin/bash
cd /home/ponto/htdocs/ponto.approsamistica.com

echo "=== FILESYSTEM CONFIG ==="
grep -E "FILESYSTEM|DISK" .env || echo "(sem config filesystem)"

echo ""
echo "=== DISK DEFAULT ==="
sudo -u ponto php artisan tinker --execute="
\$disk = config('filesystems.default');
\$root = config('filesystems.disks.' . \$disk . '.root');
echo 'disk=' . \$disk . PHP_EOL . 'root=' . \$root;
"

echo ""
echo "=== TESTE DE ESCRITA ==="
sudo -u ponto php -r "
\$path = '/home/ponto/htdocs/ponto.approsamistica.com/storage/app/tmp/faces/test.jpg';
file_put_contents(\$path, 'test');
if (file_exists(\$path)) {
    echo 'ESCRITA OK: ' . \$path . PHP_EOL;
    unlink(\$path);
} else {
    echo 'FALHA na escrita!' . PHP_EOL;
}
"
