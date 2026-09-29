<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Looks up active employees in the HR master (SyncHRIS), replacing the legacy
 * hris-api-2.php script.
 */
class HrisService
{
    /**
     * @return list<array{emp_id: string, name: string, department: ?string, username: string}>
     */
    public function searchEmployees(string $term, int $limit = 20): array
    {
        $like = '%'.$term.'%';

        return DB::connection('hris')
            ->table('ViewHREmpMaster as e')
            ->leftJoin('hrdepartment as d', 'd.deptid', '=', 'e.deptid')
            ->where('e.Active', 1)
            ->where(function ($query) use ($like) {
                $query->where('e.FullName', 'like', $like)
                    ->orWhere('e.EmpID', 'like', $like);
            })
            // The department join returns duplicate rows for some employees.
            ->distinct()
            ->orderBy('e.LName')
            ->orderBy('e.FName')
            ->limit($limit)
            ->get(['e.EmpID', 'e.FullName', 'e.FName', 'e.MName', 'e.LName', 'd.DeptDesc'])
            ->map(fn ($row) => [
                'emp_id' => $row->EmpID,
                'name' => Str::squish(str_replace(',', ' ', (string) $row->FullName)),
                'department' => $row->DeptDesc,
                'username' => self::suggestUsername($row->FName, $row->MName, $row->LName),
            ])
            ->all();
    }

    /**
     * Legacy convention: first-name initial + middle-name initial + last name, no spaces, upper case.
     */
    public static function suggestUsername(?string $first, ?string $middle, ?string $last): string
    {
        $initials = collect([$first, $middle])
            ->map(fn ($part) => Str::substr(trim((string) $part), 0, 1))
            ->implode('');

        return Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $initials.$last));
    }
}
