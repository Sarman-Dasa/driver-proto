<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->foreignId('owner_id')
                ->nullable()
                ->after('status')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('website')->nullable()->after('phone');
            $table->string('address')->nullable()->after('website');
            $table->string('city')->nullable()->after('address');
            $table->string('state')->nullable()->after('city');
            $table->string('postal_code')->nullable()->after('state');
            $table->string('country')->nullable()->after('postal_code');
            $table->string('tax_number')->nullable()->after('country');
            $table->string('currency', 3)->default('USD')->after('tax_number');
            $table->string('timezone')->default('UTC')->after('currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropForeign(['owner_id']);
            $table->dropColumn([
                'owner_id',
                'website',
                'address',
                'city',
                'state',
                'postal_code',
                'country',
                'tax_number',
                'currency',
                'timezone',
            ]);
        });
    }
};
