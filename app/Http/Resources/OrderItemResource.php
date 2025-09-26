<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
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
            'order_id' => $this->order_id,
            'product_id' => $this->product_id ?? null,
            'product_name' => $this->product_name ?? $this->name,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price ?? $this->price,
            'total_price' => ($this->quantity ?? 1) * ($this->unit_price ?? $this->price ?? 0),
            'product_image' => $this->product_image ?? $this->image ?? null,

            // If you have product relationship
            'product' => $this->whenLoaded('product', function () {
                return [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'price' => $this->product->price,
                    'image' => $this->product->image ?? null,
                    'description' => $this->product->description ?? null,
                ];
            }),

            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
