<?php

namespace App\Support\Population;

use Carbon\CarbonImmutable;
use Random\Engine\Mt19937;
use Random\Randomizer;

class PoocFamilyGenerator
{
    private Randomizer $random;

    private PoocNames $names;

    private CarbonImmutable $date;

    public function __construct(private array $config)
    {
        $this->random = new Randomizer(new Mt19937($config['seed']));
        $this->names = new PoocNames($this->random);
        $this->date = CarbonImmutable::parse($config['as_of']);
    }

    public function generate(array $purokNumbers): array
    {
        $households = [];
        $remaining = $this->config['target'];
        $numbers = array_fill_keys($purokNumbers, 0);
        while ($remaining > 0) {
            $size = min($remaining, (int) $this->names->weighted($this->config['household_sizes']));
            // A final 1-10 member household is already part of the configured distribution.
            $purok = $purokNumbers[$this->random->getInt(0, count($purokNumbers) - 1)];
            $members = $this->family($size);
            $households[] = ['purok_number' => $purok, 'household_no' => ++$numbers[$purok], 'members' => $members];
            $remaining -= $size;
        }

        return $households;
    }

    private function age(string $role, int $maximum = 94): int
    {
        $groups = array_filter($this->config['ages'][$role], fn ($group) => $group[0] <= $maximum);
        $key = $this->names->weighted(array_map(fn ($group) => $group[2], $groups));
        [$minimum, $max] = $groups[$key];

        return $this->random->getInt($minimum, min($max, $maximum));
    }

    private function sex(): string
    {
        return $this->random->getInt(0, 1) ? 'Female' : 'Male';
    }

    private function person(int $age, string $sex, string $middle, string $last, string $relationship,
        string $civil = 'Single', ?string $suffix = null, ?string $preferred = null): array
    {
        $end = $this->date->subYearsNoOverflow($age);
        $start = $this->date->subYearsNoOverflow($age + 1)->addDay();
        $dob = $start->addDays($this->random->getInt(0, (int) $start->diffInDays($end)));

        return $this->names->name($sex, $middle, $last, $suffix, $preferred) + [
            'birth_date' => $dob->toDateString(), 'sex' => $sex, 'civil_status' => $civil,
            'relationship_to_head' => $relationship, 'caregiver_index' => null, 'caregiver_relationship' => null,
            'socio' => $this->socio($age),
        ];
    }

    private function family(int $size): array
    {
        $last = $this->names->surname();
        $maiden = $this->names->surname();
        if ($size === 1) {
            $age = $this->age('independent');

            return [$this->person($age, $this->sex(), $maiden, $last, 'Head of Household', $age >= 65 ? 'Widowed' : 'Single')];
        }
        if ($size === 2) {
            $age = $this->age('independent');
            $femaleHead = $this->random->getInt(1, 100) <= $this->config['female_head_percent'];

            return [$this->person($age, $femaleHead ? 'Female' : 'Male', $maiden, $last, 'Head of Household', 'Married'),
                $this->person(max(18, min(94, $age + $this->random->getInt(-5, 5))), $femaleHead ? 'Male' : 'Female', $this->names->surname(), $last, 'Spouse', 'Married')];
        }
        $type = $this->names->weighted($this->config['family_types']);
        if ($type === 'grandparents') {
            $age = $this->age('grandparent');
            $sex = $this->random->getInt(1, 100) <= 75 ? 'Female' : 'Male';
            $members = [$this->person($age, $sex, $maiden, $last, 'Head of Household', 'Widowed')];
            $childMiddle = $this->names->surname();
            for ($i = 1; $i < $size; $i++) {
                $child = $this->person($this->age('child', 17), $this->sex(), $childMiddle, $last, 'Grandchild');
                $members[] = $this->caregiver($child, 0, $sex === 'Female' ? 'Grandmother' : 'Grandfather');
            }

            return $members;
        }
        $parentAge = max($this->age('parent'), 24 + max(0, $size - 6));
        if ($type === 'single_parent') {
            $sex = $this->random->getInt(1, 100) <= 80 ? 'Female' : 'Male';
            $civil = $this->names->weighted(['Single' => 45, 'Separated' => 35, 'Widowed' => 20]);
            $head = $this->person($parentAge, $sex, $maiden, $last, 'Head of Household', $civil);
            $members = [$head];
            for ($i = 1; $i < $size; $i++) {
                $child = $this->person($this->age('child', $parentAge - 19), $this->sex(), $maiden, $last, 'Child');
                $members[] = $this->caregiver($child, 0, $sex === 'Female' ? 'Mother' : 'Father');
            }

            return $members;
        }
        $unmarried = $type !== 'blended' && $this->random->getInt(1, 100) <= $this->config['unmarried_couple_percent'];
        $femaleHead = $this->random->getInt(1, 100) <= $this->config['female_head_percent'];
        $junior = ! $unmarried && $type !== 'blended' && $this->random->getInt(1, 100) <= 4;
        $father = $this->person($parentAge + 2, 'Male', $this->names->surname(), $last, $femaleHead ? 'Spouse' : 'Head of Household', $unmarried ? 'Single' : 'Married', $junior ? 'Sr.' : null);
        $mother = $this->person($parentAge, 'Female', $unmarried ? $this->names->surname() : $maiden, $unmarried ? $maiden : $last,
            $femaleHead ? 'Head of Household' : 'Spouse', $unmarried ? 'Single' : 'Married');
        $members = $femaleHead ? [$mother, $father] : [$father, $mother];
        $motherIndex = $femaleHead ? 0 : 1;
        $motherSurname = $unmarried ? $mother['last_name'] : $mother['middle_name'];
        if ($type === 'extended' && $size >= 4) {
            $members[] = $this->person(min(94, $parentAge + $this->random->getInt(22, 29)), 'Female', $father['middle_name'], $last, $femaleHead ? 'Other Relative' : 'Parent', 'Widowed');
        }
        $stepSurname = $this->names->surname();
        while (count($members) < $size) {
            $firstChild = count($members) === ($type === 'extended' && $size >= 4 ? 3 : 2);
            $childSex = $junior && $firstChild ? 'Male' : $this->sex();
            $usesMotherSurname = $unmarried && $this->random->getInt(1, 100) <= 40;
            $childLast = $usesMotherSurname ? $mother['last_name'] : ($type === 'blended' && $firstChild ? $stepSurname : $last);
            $childMiddle = $usesMotherSurname ? $mother['middle_name'] : $motherSurname;
            $child = $this->person($this->age('child', $parentAge - 19), $childSex, $childMiddle, $childLast,
                $type === 'blended' && $firstChild && ! $femaleHead ? 'Other Relative' : 'Child', 'Single',
                $junior && $firstChild ? 'Jr.' : null, $junior && $firstChild ? $father['first_name'] : null);
            $members[] = $this->caregiver($child, $motherIndex, 'Mother');
        }

        return $members;
    }

    private function caregiver(array $child, int $index, string $relationship): array
    {
        if (CarbonImmutable::parse($child['birth_date'])->addYearsNoOverflow(5)->gt($this->date)
            && $this->random->getInt(1, 100) > $this->config['missing_caregiver_percent']) {
            $child['caregiver_index'] = $index;
            $child['caregiver_relationship'] = $relationship;
        }

        return $child;
    }

    private function socio(int $age): array
    {
        if ($age < 6) {
            return ['occupation' => null, 'employment_status' => 'N/A', 'highest_education_level' => 'None', 'education_status' => 'N/A'];
        }
        if ($age < 18 || ($age < 23 && $this->random->getInt(1, 100) <= 40)) {
            return ['occupation' => 'Student', 'employment_status' => 'N/A', 'highest_education_level' => $age < 12 ? 'Elementary' : ($age < 18 ? 'High School' : 'College'), 'education_status' => 'Undergraduate'];
        }
        $education = $this->names->weighted(['Elementary' => 20, 'High School' => 55, 'Vocational' => 15, 'College' => $age >= 22 ? 10 : 0]);
        if ($age >= 65 && $this->random->getInt(1, 100) <= 60) {
            $job = 'Retired';
        } elseif ($this->random->getInt(1, 100) <= 22) {
            $job = null;
        } else {
            $job = $this->config['occupations'][$this->random->getInt(0, count($this->config['occupations']) - 1)];
        }
        if ($job === 'Teacher' && $age < 22) {
            $job = 'Service Worker';
        }
        if ($job === 'Teacher') {
            $education = 'College';
        }

        return ['occupation' => $job, 'employment_status' => in_array($job, [null, 'Homemaker', 'Retired'], true) ? 'Unemployed' : 'Employed',
            'highest_education_level' => $education, 'education_status' => 'Graduate'];
    }
}
