<?php

declare(strict_types=1);

namespace Tests\Support\SensitiveEncrypted;

use App\Casts\SensitiveEncrypted;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string|null $secret
 */
class SensitiveProofHost extends Model
{
    protected $table = 'sensitive_proof_hosts';

    /**
     * @var list<string>
     */
    protected $fillable = ['secret'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'secret' => SensitiveEncrypted::class,
        ];
    }
}
