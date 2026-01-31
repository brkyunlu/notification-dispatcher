<?php

namespace App\Modules\Notification\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class NotificationCollection extends ResourceCollection
{
    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse($request): JsonResponse
    {
        if ($this->resource instanceof \Illuminate\Pagination\AbstractPaginator) {
            return response()->json([
                'success' => true,
                'data' => $this->collection->toArray(),
                'meta' => [
                    'total' => $this->resource->total(),
                    'per_page' => $this->resource->perPage(),
                    'current_page' => $this->resource->currentPage(),
                    'last_page' => $this->resource->lastPage(),
                    'from' => $this->resource->firstItem(),
                    'to' => $this->resource->lastItem(),
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $this->collection->toArray(),
        ]);
    }
}
