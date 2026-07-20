<?php
namespace App\Services\Compliance;
use App\Models\Compliance\GstEwayReadiness;
use App\Models\Crm\CrmInvoice;
use App\Models\User;

class EwayBillReadinessService
{
    /** @return array{ready:bool,review_required:bool,errors:array<int,string>,payload:array<string,mixed>|null} */
    public function validate(CrmInvoice $invoice, GstEwayReadiness $readiness): array
    {
        $errors=[]; if (!$readiness->document_date) $errors[]='Document date is missing.'; if (!$readiness->transport_mode) $errors[]='Transport mode is required.'; if (!$readiness->transport_distance) $errors[]='Transport distance is required.'; if (!$readiness->dispatch_from || !$readiness->ship_to) $errors[]='Dispatch-from and ship-to details are required.';
        $review = (float)$invoice->grand_total >= 50000 || $invoice->place_of_supply_state_code !== $invoice->supplier_state_code_snapshot;
        return ['ready'=>$errors===[], 'review_required'=>$review, 'errors'=>$errors, 'payload'=>$errors?null:['document_number'=>$invoice->invoice_number,'document_date'=>$invoice->issue_date?->format('d/m/Y'),'supply_type'=>'outward','total_value'=>$invoice->grand_total,'transport_mode'=>$readiness->transport_mode,'distance_km'=>$readiness->transport_distance,'dispatch_from'=>$readiness->dispatch_from,'ship_to'=>$readiness->ship_to]];
    }
    public function recordValidation(CrmInvoice $invoice, User $user, array $data): GstEwayReadiness { return GstEwayReadiness::updateOrCreate(['company_id'=>$user->company_id,'document_model'=>CrmInvoice::class,'document_id'=>$invoice->id],$data+['document_type'=>'sales_invoice','document_date'=>$invoice->issue_date,'validated_at'=>now(),'validated_by'=>$user->id,'status'=>'validation_failed']); }
}
