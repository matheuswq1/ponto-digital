<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','gestor','funcionario','totem') NOT NULL DEFAULT 'funcionario'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','gestor','funcionario') NOT NULL DEFAULT 'funcionario'");
    }
};
