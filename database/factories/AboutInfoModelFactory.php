<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Features\Portfolio\Infra\Models\AboutInfoModel;
use Illuminate\Database\Eloquent\Factories\Factory;

final class AboutInfoModelFactory extends Factory
{
    protected $model = AboutInfoModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'photo' => null,
            'photo_alt' => null,
            'show_location' => true,
            'timezone' => 'Europe/Berlin',
            'relocation_readiness' => false,
            'availability_status' => 'available',
            'work_type' => 'remote',
        ];
    }
}
