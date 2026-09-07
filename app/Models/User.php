<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use App\UserRoleEnum;
use App\Models\EmpAccountsRecord;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The employee/role-assignment record linked to this login account, if
     * an admin has assigned one. A user can register and log in without
     * this existing yet — it's set separately by an admin on the Employee
     * Accounts page.
     */
    public function employeeRecord()
    {
        return $this->hasOne(EmpAccountsRecord::class, 'user_id');
    }

    /**
     * Guarantees exactly one path to Super Admin access always exists on
     * emp_accounts_record: whenever a new User is created, if no Super
     * Admin (accLevel 'S') exists anywhere yet, this account becomes one
     * automatically.
     *
     * This checks for an existing Super Admin directly rather than "is
     * this literally the first user ever" — the latter breaks silently
     * on any database that isn't perfectly empty (e.g. leftover/seeded
     * rows from a transferred dump), which is exactly the failure mode
     * this replaces. As long as no one holds 'S' yet, the very next
     * registration — on a fresh install or an existing one — claims it.
     *
     * Deliberately does NOT copy the login password into
     * emp_accounts_record — real authentication lives solely on the
     * users table via user_id; the password/username/Email columns on
     * emp_accounts_record are legacy and intentionally left unused (see
     * EmpAccountsRecord's $fillable comment).
     */
    protected static function booted(): void
    {
        static::created(function (User $user) {
            if (EmpAccountsRecord::where('accLevel', 'S')->exists()) {
                return;
            }

            if ($user->employeeRecord()->exists()) {
                return;
            }

            EmpAccountsRecord::create([
                'user_id'  => $user->id,
                'accLevel' => 'S',
                'fullName' => Str::limit($user->name, 25, ''),
                'Age'      => 18,
                'Gender'   => 'Other',
                'Address'  => 'N/A',
                'Pos'      => 'Administrator',
                'Mobile'   => '00000000000',
            ]);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRoleEnum::class,
        ];
    }
}