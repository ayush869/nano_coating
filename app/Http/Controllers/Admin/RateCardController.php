<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coating;
use App\Models\Material;
use App\Services\RateCard;
use Illuminate\Http\Request;

/**
 * One screen for all seven rate-card tables. Rows can be added, edited and
 * deactivated; never deleted, because saved quotes reference them.
 */
class RateCardController extends Controller
{
    public const TABLES = [
        'coatings' => ['label' => 'Coatings', 'value' => 'Rate (₹ / sq ft)', 'step' => '0.01', 'hint' => 'Base coating price per coated square foot.'],
        'addons' => ['label' => 'Add-on layers', 'value' => 'Rate (₹ / sq ft)', 'step' => '0.01', 'hint' => 'Added to the coating rate when chosen on a line.'],
        'materials' => ['label' => 'Material factors', 'value' => 'Factor (×)', 'step' => '0.001', 'hint' => 'Product absorption. 1.00 = normal, 1.20 = uses 20% more.'],
        'designs' => ['label' => 'Design factors', 'value' => 'Factor (×)', 'step' => '0.001', 'hint' => 'Labour difficulty. 1.00 = plain.'],
    ];

    public function index(string $table)
    {
        $meta = $this->meta($table);
        $class = RateCard::MASTERS[$table];

        return view('admin.rates', [
            'table' => $table,
            'meta' => $meta,
            'column' => $class::VALUE_COLUMN,
            'rows' => $class::query()->ordered()->get(),
            'tables' => self::TABLES,
            'coatings' => $table === 'coatings' ? Coating::with('materials:id')->ordered()->get() : null,
            'materials' => $table === 'coatings' ? Material::active()->ordered()->get() : null,
        ]);
    }

    public function store(Request $request, string $table)
    {
        $this->meta($table);
        $class = RateCard::MASTERS[$table];
        $class::create($this->validated($request, $class::VALUE_COLUMN) + ['active' => true]);

        return back()->with('ok', 'Added.');
    }

    public function update(Request $request, string $table, int $id)
    {
        $this->meta($table);
        $class = RateCard::MASTERS[$table];
        $row = $class::findOrFail($id);
        $row->update($this->validated($request, $class::VALUE_COLUMN) + ['active' => $request->boolean('active')]);

        return back()->with('ok', "{$row->name} saved.");
    }

    /** Allowed coating × material combinations. No ticks = any material. */
    public function combinations(Request $request)
    {
        $data = $request->validate(['allow' => ['array'], 'allow.*' => ['array'], 'allow.*.*' => ['integer', 'exists:materials,id']]);

        foreach (Coating::all() as $coating) {
            $coating->materials()->sync($data['allow'][$coating->id] ?? []);
        }

        return back()->with('ok', 'Allowed combinations saved.');
    }

    private function meta(string $table): array
    {
        abort_unless(isset(self::TABLES[$table]), 404);

        return self::TABLES[$table];
    }

    private function validated(Request $request, string $column): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            $column => ['required', 'numeric', 'min:0', 'max:999999'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
