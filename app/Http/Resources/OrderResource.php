<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'total' => $this->total,
            'status' => $this->status,
            'is_paid' => $this->is_paid,
            'is_customized' => $this->is_customized,
            'customized_file' => $this->when($this->customized_file, function () {
                return asset('storage/' . $this->customized_file);
            }),
            'payment_status' => $this->is_paid ? 'Paid' : 'Pending',
            'order_type' => $this->is_customized ? 'Customized' : 'Simple',
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            'items' => OrderItemResource::collection($this->whenLoaded('orderItems')),
            'items_count' => $this->whenCounted('orderItems'),
        ];
    }
}
