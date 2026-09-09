<?php

interface PropertyCoreStore
{
    public function listInventory(array $filters): array;
    public function findInventory(string $businessId): array;
    public function inventorySummary(): array;
    public function inventoryOptions(array $filters): array;
    public function nextInventoryBusinessId(): string;
    public function createInventory(array $input, string $actor): array;
    public function updateInventory(
        string $businessId,
        array $input,
        string $actor
    ): array;
    public function deleteInventory(string $businessId, string $actor): array;

    public function listAssets(array $filters): array;
    public function findAsset(string $businessId): array;
    public function assetSummary(): array;
    public function assetOptions(array $filters): array;
    public function assetFilters(): array;
    public function assetSuggestions(array $filters): array;
    public function lifecycleConfiguration(): array;
    public function updateLifecycleSettings(array $input, string $actor): array;
    public function saveCategoryUsefulLife(array $input, string $actor): array;
    public function listDispositions(array $filters): array;
    public function findDisposition(string $dispositionId): array;
    public function dispositionReview(string $assetBusinessId): array;
    public function createDisposition(
        string $assetBusinessId,
        array $input,
        string $actor
    ): array;
    public function approveDisposition(
        string $dispositionId,
        array $input,
        string $actor
    ): array;
    public function completeDisposition(
        string $dispositionId,
        array $input,
        string $actor
    ): array;
    public function rejectDisposition(
        string $dispositionId,
        array $input,
        string $actor
    ): array;
    public function cancelDisposition(
        string $dispositionId,
        array $input,
        string $actor
    ): array;
    public function nextAssetBusinessId(): string;
    public function registerAsset(array $input, string $actor): array;
    public function updateAsset(
        string $businessId,
        array $input,
        string $actor
    ): array;
    public function assignAsset(
        string $businessId,
        array $input,
        string $actor
    ): array;
    public function returnAsset(string $businessId, string $actor): array;
    public function deleteAsset(string $businessId, string $actor): array;
}
