<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegisterUserAction
{
    /**
     * Execute the registration action.
     *
     * @param  array{name: string, email: string, password: string, company_name?: ?string}  $data
     * @return array{user: User, token: string, company: ?Company}
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $companyName = ! empty($data['company_name'])
                ? trim($data['company_name'])
                : $user->name."'s Workspace";

            $baseSlug = Str::slug($companyName);
            $slug = $baseSlug ?: 'workspace';
            $uniqueSlug = $slug.'-'.Str::lower(Str::random(6));

            $company = Company::create([
                'name' => $companyName,
                'slug' => $uniqueSlug,
                'email' => $user->email,
                'status' => CompanyStatus::Active,
            ]);

            $user->companies()->attach($company->id, ['role_id' => null]);

            event(new Registered($user));

            $token = $user->createToken('auth_token')->plainTextToken;

            return [
                'user' => $user->load('companies'),
                'token' => $token,
                'company' => $company,
            ];
        });
    }
}
