<?php

namespace App\Enums\Crm;

enum DemoMeetingMode: string
{
    case PhoneCall = 'phone_call';
    case GoogleMeetLater = 'google_meet_later';
    case InPerson = 'in_person';
    case ExternalLink = 'external_link';

    public function label(): string
    {
        return match ($this) {
            self::PhoneCall => 'Phone Call',
            self::GoogleMeetLater => 'Google Meet Later',
            self::InPerson => 'In Person',
            self::ExternalLink => 'Zoom / External Link',
        };
    }
}
