<?php

namespace Tests\Unit;

use App\Models\Household;
use App\Models\Resident;
use App\Support\HouseholdMemberOrdering;
use App\Support\HouseholdRelationships;
use Tests\TestCase;

class HouseholdMemberOrderingTest extends TestCase
{
    private function member(int $id, string $name, string $relationship, ?string $dob = '1990-01-01'): Resident
    {
        return (new Resident)->setRawAttributes(['id' => $id, 'last_name' => 'Pilot', 'first_name' => $name,
            'middle_name' => null, 'suffix' => null, 'relationship_to_head' => $relationship, 'birth_date' => $dob]);
    }

    private function home(array $members, ?int $head = null): Household
    {
        return (new Household(['head_resident_id' => $head]))->setRelation('residents', collect($members));
    }

    public function test_every_canonical_and_observed_legacy_category_is_explicit(): void
    {
        $groups = HouseholdRelationships::groups();
        foreach (['Spouse / Partner', 'Spouse'] as $value) {
            $this->assertSame(1, HouseholdRelationships::presentationCategory($value));
        }
        foreach (['Son', 'Daughter', 'Stepson', 'Stepdaughter', 'Child'] as $value) {
            $this->assertSame(2, HouseholdRelationships::presentationCategory($value));
        }
        foreach ([...$groups['Ascendants / Descendants'], ...$groups['Other Relatives'], 'Son-in-law', 'Daughter-in-law', 'Parent', 'Grandchild', 'Sibling'] as $value) {
            $this->assertSame(3, HouseholdRelationships::presentationCategory($value), $value);
        }
        foreach ($groups['Non-Relatives'] as $value) {
            $this->assertSame(4, HouseholdRelationships::presentationCategory($value), $value);
        }
        foreach (['Member', 'Self', 'Unrecognized future value', 'Head', 'Head of Household', 'Household Head', '', null] as $value) {
            $this->assertSame(5, HouseholdRelationships::presentationCategory($value));
        }
        $this->assertSame(1, HouseholdRelationships::presentationCategory(' SPOUSE '));
    }

    public function test_only_fk_designates_head_and_categories_follow_approved_order(): void
    {
        $members = [
            $this->member(9, 'ApparentHead', 'Head'), $this->member(8, 'Unknown', 'Self'),
            $this->member(7, 'Foster', 'Foster Child'), $this->member(6, 'Relative', 'Son-in-law'),
            $this->member(5, 'Young', 'Daughter', '2020-01-01'), $this->member(4, 'Old', 'Child', '2000-01-01'),
            $this->member(3, 'Spouse', 'Spouse'), $this->member(2, 'ActualHead', 'Friend'),
        ];
        $home = $this->home($members, 2);
        $before = array_map(fn ($r) => $r->getAttributes(), $members);
        $this->assertSame([2, 3, 4, 5, 6, 7, 9, 8], HouseholdMemberOrdering::ordered($home)->pluck('id')->all());
        $this->assertSame($before, array_map(fn ($r) => $r->getAttributes(), $members));
        $this->assertSame(2, $home->head_resident_id);
        $this->assertSame($members, $home->residents->all());
    }

    public function test_children_use_dob_then_normalized_name_and_id_with_invalid_dates_last(): void
    {
        $home = $this->home([
            $this->member(8, 'Zulu', 'Son', null), $this->member(7, 'Alpha', 'Child', '2020-02-30'),
            $this->member(6, 'Alpha', 'Daughter', null), $this->member(5, 'Future', 'Child', '2999-01-01'),
            $this->member(4, 'Beta', 'Daughter', '2010-01-01'), $this->member(3, ' alpha ', 'Stepson', '2010-01-01'),
            $this->member(2, 'Alpha', 'Stepdaughter', '2010-01-01'), $this->member(1, 'Older', 'Son', '2000-01-01'),
        ]);
        $this->assertSame([1, 2, 3, 4, 6, 7, 5, 8], HouseholdMemberOrdering::ordered($home)->pluck('id')->all());
    }

    public function test_headless_empty_and_single_member_households_do_not_infer_a_head(): void
    {
        $this->assertSame([], HouseholdMemberOrdering::ordered($this->home([]))->all());
        $this->assertSame([1], HouseholdMemberOrdering::ordered($this->home([$this->member(1, 'Only', 'Head')]))->pluck('id')->all());
        $home = $this->home([$this->member(1, 'Alpha', 'Head'), $this->member(2, 'Zulu', 'Spouse')]);
        $this->assertSame([2, 1], HouseholdMemberOrdering::ordered($home)->pluck('id')->all());
        $this->assertNull($home->head_resident_id);
    }

    public function test_members_sort_globally_before_twelve_row_chunks_without_excluding_lifecycle_states(): void
    {
        $members = [];
        foreach (range(1, 15) as $id) {
            $members[] = $this->member($id, sprintf('Child%02d', $id), 'Child', sprintf('%04d-01-01', 2000 + $id));
        }
        $members[14]->setAttribute('is_active', false)->setAttribute('resident_status', 'deceased');
        $home = $this->home(array_reverse($members), 15);
        $chunks = HouseholdMemberOrdering::ordered($home)->chunk(12);
        $this->assertSame([12, 3], $chunks->map->count()->values()->all());
        $this->assertSame([15, ...range(1, 11)], $chunks[0]->pluck('id')->all());
        $this->assertSame([12, 13, 14], $chunks[1]->pluck('id')->all());
    }
}
