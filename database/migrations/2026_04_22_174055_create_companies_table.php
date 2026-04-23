<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('cnpj', 18)->unique();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 2)->nullable();
            $table->string('zipcode', 10)->nullable();
            $table->string('logo_url')->nullable();
            $table->boolean('active')->default(true);
            $table->decimal('latitude', 10, 7)->nullable()->comment('Coordenada para geofencing');
            $table->decimal('longitude', 10, 7)->nullable()->comment('Coordenada para geofencing');
            $table->integer('geofence_radius')->default(500)->comment('Raio em metros');
            $table->boolean('require_photo')->default(true);
            $table->boolean('require_geolocation')->default(true);
            $table->time('work_start')->default('08:00:00');
            $table->time('work_end')->default('18:00:00');
            $table->integer('lunch_duration')->default(60)->comment('Duração do almoço em minutos');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
