<?php

namespace App\Repositories\Integrations;

use App\Models\IntegrationConnection;
use App\Models\User;

class IntegrationConnectionRepository
{
    public const GOOGLE_CALENDAR = 'google_calendar';

    public function googleCalendarForCompany(int $companyId): ?IntegrationConnection
    {
        return IntegrationConnection::query()
            ->where('company_id', $companyId)
            ->where('provider', self::GOOGLE_CALENDAR)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateGoogleCalendar(User $user, array $attributes): IntegrationConnection
    {
        return IntegrationConnection::updateOrCreate(
            [
                'company_id' => $user->company_id,
                'provider' => self::GOOGLE_CALENDAR,
            ],
            $attributes + [
                'name' => 'Google Calendar',
            ],
        );
    }
}
