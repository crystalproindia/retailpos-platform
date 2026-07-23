<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Crm\CrmActivity;
use App\Models\Crm\CrmCompany;
use App\Models\Crm\CrmContact;
use App\Models\Crm\CrmCustomer;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmQuotation;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerGroup;
use App\Models\Customers\CustomerSetting;
use App\Models\Inventory\InventoryBrand;
use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Purchases\GoodsReceipt;
use App\Models\Purchases\PurchaseOrder;
use App\Models\Purchases\PurchaseRequest;
use App\Models\Purchases\PurchaseReturn;
use App\Models\Purchases\Supplier;
use App\Models\Promotions\PromotionCampaign;
use App\Models\Promotions\PromotionRule;
use App\Models\Promotions\PromotionSettings;
use App\Models\Pos\PosSale;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'legal_name', 'trade_name', 'tax_id', 'email', 'phone', 'address', 'city', 'state', 'country', 'postal_code', 'timezone', 'currency', 'tax_registration_type', 'industry', 'billing_contact_name', 'billing_contact_email', 'is_active'])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class);
    }

    public function dashboardStatistics(): HasMany
    {
        return $this->hasMany(DashboardStatistic::class);
    }

    public function crmCompanies(): HasMany
    {
        return $this->hasMany(CrmCompany::class);
    }

    public function crmContacts(): HasMany
    {
        return $this->hasMany(CrmContact::class);
    }

    public function crmLeads(): HasMany
    {
        return $this->hasMany(CrmLead::class);
    }

    public function crmQuotations(): HasMany
    {
        return $this->hasMany(CrmQuotation::class);
    }

    public function crmCustomers(): HasMany
    {
        return $this->hasMany(CrmCustomer::class);
    }

    public function crmActivities(): HasMany
    {
        return $this->hasMany(CrmActivity::class);
    }

    public function inventoryCategories(): HasMany
    {
        return $this->hasMany(InventoryCategory::class);
    }

    public function inventoryBrands(): HasMany
    {
        return $this->hasMany(InventoryBrand::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function purchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function purchaseReturns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    public function promotionCampaigns(): HasMany
    {
        return $this->hasMany(PromotionCampaign::class);
    }

    public function promotionRules(): HasMany
    {
        return $this->hasMany(PromotionRule::class);
    }

    public function promotionSettings(): HasMany
    {
        return $this->hasMany(PromotionSettings::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function customerGroups(): HasMany
    {
        return $this->hasMany(CustomerGroup::class);
    }

    public function customerSettings(): HasMany
    {
        return $this->hasMany(CustomerSetting::class);
    }

    public function posSales(): HasMany
    {
        return $this->hasMany(PosSale::class);
    }

    public function saasSubscriptions(): HasMany
    {
        return $this->hasMany(SaasSubscription::class);
    }

    public function saasSubscriptionInvoices(): HasMany
    {
        return $this->hasMany(SaasSubscriptionInvoice::class);
    }

    public function saasBillingPayments(): HasMany
    {
        return $this->hasMany(SaasBillingPayment::class);
    }

    public function saasBillingCheckoutSessions(): HasMany
    {
        return $this->hasMany(SaasBillingCheckoutSession::class);
    }

    public function saasResellerAssignments(): HasMany
    {
        return $this->hasMany(SaasResellerTenantAssignment::class);
    }
}
