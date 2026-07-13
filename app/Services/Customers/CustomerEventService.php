<?php
namespace App\Services\Customers; use App\Events\Domain\Customers\CustomerDomainEvent; use App\Models\User; use App\Services\Events\DomainEventDispatcher; use Illuminate\Database\Eloquent\Model;
class CustomerEventService { public function __construct(private readonly DomainEventDispatcher $events){} /** @param array<string,mixed> $payload */ public function dispatch(string $key,User $user,?Model $model=null,array $payload=[]):void{$this->events->dispatch(new CustomerDomainEvent($key,$user->company_id,$user->id,$model?->getMorphClass(),$model?->getKey(),$payload));} }
