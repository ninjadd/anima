<?php

namespace Anima\Http\Controllers;

use Anima\Contracts\PayloadStorageInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EntryController
{
    /**
     * Create a new entry controller instance.
     */
    public function __construct(
        protected PayloadStorageInterface $storage
    ) {}

    /**
     * List paginated and filtered webhook entries.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 25);
        $filters = $request->all();

        $results = $this->storage->paginate($perPage, $filters);

        return response()->json($results);
    }

    /**
     * Retrieve a single webhook entry by ID.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        $entry = $this->storage->find($id);

        if (! $entry) {
            return response()->json([
                'message' => 'Webhook entry not found.',
            ], 404);
        }

        return response()->json($entry);
    }

    /**
     * Delete a single webhook entry.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        $deleted = $this->storage->delete($id);

        return response()->json([
            'deleted' => $deleted,
        ]);
    }

    /**
     * Purge all captured webhook entries.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function clear(): JsonResponse
    {
        $purged = $this->storage->purge();

        return response()->json([
            'purged' => $purged,
        ]);
    }
}
