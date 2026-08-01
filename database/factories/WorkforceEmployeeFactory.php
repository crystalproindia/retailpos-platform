<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Company;
use App\Models\WorkforceEmployee;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkforceEmployee> */
class WorkforceEmployeeFactory extends Factory
{
    protected $model = WorkforceEmployee::class;
    public function definition(): array
    {
        $company = Company::factory();
        return ['company_id' => $company, 'primary_branch_id' => Branch::factory()->for($company), 'employee_number' => 'EMP-'.$this->faker->unique()->numerify('####'), 'first_name' => $this->faker->firstName(), 'last_name' => $this->faker->lastName(), 'display_name' => $this->faker->name(), 'status' => 'active'];
    }
}
