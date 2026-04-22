<?php

namespace CaringPays\CareAdvisor\Grounding;

final class EligibilityMatrixService
{
    /**
     * @param array<string,string> $answers
     * @return array<string,bool>
     */
    public function evaluate(string $state, array $answers): array
    {
        $normalizedState = strtolower(trim($state));

        return [
            'ga_ccsp' => $normalizedState === 'georgia' ? $this->evaluateGaCcsp($answers) : false,
            'ga_source' => $normalizedState === 'georgia' ? $this->evaluateGaSource($answers) : false,
            'ma_afc' => $normalizedState === 'massachusetts' ? $this->evaluateMaAfc($answers) : false,
        ];
    }

    /**
     * @param array<string,string> $answers
     */
    private function evaluateGaCcsp(array $answers): bool
    {
        $rules = $this->loadGroupRules('georgia', 'ga_ccsp');
        if ($rules === null) {
            return false;
        }

        $income = $this->findNumericAnswer($answers, ['income', 'monthly_income', 'household_income', 'gross_monthly_income']);
        if ($income === null) {
            return false;
        }

        $incomeMax = (float) ($rules['income_max'] ?? 0);
        if ($incomeMax <= 0 || $income > $incomeMax) {
            return false;
        }

        if (($rules['nhloc_required'] ?? true) === false) {
            return true;
        }

        return $this->isTruthy($this->findAnswer($answers, ['nhloc', 'needs_nhloc', 'nursing_home_level_of_care']));
    }

    /**
     * @param array<string,string> $answers
     */
    private function evaluateGaSource(array $answers): bool
    {
        $rules = $this->loadGroupRules('georgia', 'ga_source');
        if ($rules === null) {
            return false;
        }

        $income = $this->findNumericAnswer($answers, ['income', 'monthly_income', 'household_income', 'gross_monthly_income']);
        if ($income === null) {
            return false;
        }

        $threshold = (float) ($rules['ssi_income_threshold'] ?? 0);
        if ($threshold <= 0 || $income > $threshold) {
            return false;
        }

        if (($rules['ssi_linked_required'] ?? true) && ! $this->isTruthy($this->findAnswer($answers, ['ssi_linked', 'receives_ssi', 'on_ssi']))) {
            return false;
        }

        if (($rules['chronic_condition_required'] ?? true) && ! $this->isTruthy($this->findAnswer($answers, ['chronic_condition', 'has_chronic_condition']))) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string,string> $answers
     */
    private function evaluateMaAfc(array $answers): bool
    {
        $rules = $this->loadGroupRules('massachusetts', 'ma_afc');
        if ($rules === null) {
            return false;
        }

        $age = $this->findNumericAnswer($answers, ['age', 'applicant_age']);
        if ($age === null) {
            return false;
        }

        $minimumAge = (float) ($rules['minimum_age'] ?? 0);
        if ($minimumAge <= 0 || $age < $minimumAge) {
            return false;
        }

        if (($rules['adl_assistance_required'] ?? true) === false) {
            return true;
        }

        return $this->isTruthy($this->findAnswer($answers, ['adl_assistance', 'needs_adl_assistance', 'adl_help_required']));
    }

    /**
     * @return array<string,mixed>|null
     */
    private function loadGroupRules(string $state, string $group): ?array
    {
        global $wpdb;

        if (! isset($wpdb)) {
            return null;
        }

        $table = $wpdb->prefix . 'cp_eligibility_matrix';
        $payload = $wpdb->get_var($wpdb->prepare(
            "SELECT matrix_payload
            FROM {$table}
            WHERE state = %s
              AND eligibility_group = %s
              AND status = %s
            ORDER BY effective_at DESC, id DESC
            LIMIT 1",
            $state,
            $group,
            'active'
        ));

        if (! is_string($payload) || $payload === '') {
            return null;
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string,string> $answers
     * @param string[] $keys
     */
    private function findAnswer(array $answers, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($answers[$key]) && is_string($answers[$key])) {
                return $answers[$key];
            }
        }

        return '';
    }

    /**
     * @param array<string,string> $answers
     * @param string[] $keys
     */
    private function findNumericAnswer(array $answers, array $keys): ?float
    {
        $value = $this->findAnswer($answers, $keys);
        if ($value === '') {
            return null;
        }

        $sanitized = preg_replace('/[^0-9.\-]/', '', $value);
        if (! is_string($sanitized) || $sanitized === '' || ! is_numeric($sanitized)) {
            return null;
        }

        return (float) $sanitized;
    }

    private function isTruthy(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['yes', 'true', '1', 'eligible'], true);
    }
}
