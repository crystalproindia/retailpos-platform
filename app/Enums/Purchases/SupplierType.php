<?php

namespace App\Enums\Purchases;

enum SupplierType: string
{
    case Manufacturer = 'manufacturer';
    case Distributor = 'distributor';
    case Wholesaler = 'wholesaler';
    case LocalVendor = 'local_vendor';
    case Importer = 'importer';
    case ServiceProvider = 'service_provider';
    case Other = 'other';
}
