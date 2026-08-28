<?php

interface ProcurementStore
{
    public function list(array $filters): array;
    public function find(string $businessId): array;
    public function nextBusinessId(): string;
    public function create(array $input, string $actor): array;
    public function update(string $businessId, array $input, string $actor): array;
    public function delete(string $businessId, string $actor): array;
    public function summary(): array;
}
