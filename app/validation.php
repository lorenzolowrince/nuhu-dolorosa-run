<?php
declare(strict_types=1);

// ============================================================
// Input Validation
// ============================================================

class Validation
{
    private array $errors = [];
    private array $data   = [];

    public function __construct(array $input)
    {
        // Sanitize all string inputs on intake
        foreach ($input as $key => $value) {
            $this->data[$key] = is_string($value) ? sanitize($value) : $value;
        }
    }

    // --- Retrieve validated/sanitized value ---
    public function get(string $field, mixed $default = ''): mixed
    {
        return $this->data[$field] ?? $default;
    }

    public function all(): array
    {
        return $this->data;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function firstError(string $field): string
    {
        return $this->errors[$field] ?? '';
    }

    // ---- Rule methods (chainable) ----

    public function required(string $field, string $label = ''): static
    {
        $label = $label ?: ucwords(str_replace('_', ' ', $field));
        $value = $this->data[$field] ?? '';
        if ($value === '' || $value === null) {
            $this->errors[$field] = "{$label} is required.";
        }
        return $this;
    }

    public function minLength(string $field, int $min, string $label = ''): static
    {
        $label = $label ?: ucwords(str_replace('_', ' ', $field));
        $value = $this->data[$field] ?? '';
        if (mb_strlen((string)$value) < $min) {
            $this->errors[$field] = "{$label} must be at least {$min} characters.";
        }
        return $this;
    }

    public function maxLength(string $field, int $max, string $label = ''): static
    {
        $label = $label ?: ucwords(str_replace('_', ' ', $field));
        $value = $this->data[$field] ?? '';
        if (mb_strlen((string)$value) > $max) {
            $this->errors[$field] = "{$label} must not exceed {$max} characters.";
        }
        return $this;
    }

    public function email(string $field, string $label = 'Email'): static
    {
        $value = $this->data[$field] ?? '';
        if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "{$label} must be a valid email address.";
        }
        return $this;
    }

    public function numeric(string $field, string $label = ''): static
    {
        $label = $label ?: ucwords(str_replace('_', ' ', $field));
        $value = $this->data[$field] ?? '';
        if ($value !== '' && !is_numeric($value)) {
            $this->errors[$field] = "{$label} must be a number.";
        }
        return $this;
    }

    public function in(string $field, array $allowed, string $label = ''): static
    {
        $label = $label ?: ucwords(str_replace('_', ' ', $field));
        $value = $this->data[$field] ?? '';
        if ($value !== '' && !in_array($value, $allowed, true)) {
            $this->errors[$field] = "{$label} contains an invalid value.";
        }
        return $this;
    }

    public function date(string $field, string $format = 'Y-m-d', string $label = ''): static
    {
        $label = $label ?: ucwords(str_replace('_', ' ', $field));
        $value = $this->data[$field] ?? '';
        if ($value !== '') {
            $d = DateTime::createFromFormat($format, $value);
            if (!$d || $d->format($format) !== $value) {
                $this->errors[$field] = "{$label} must be a valid date.";
            }
        }
        return $this;
    }

    public function regex(string $field, string $pattern, string $message): static
    {
        $value = $this->data[$field] ?? '';
        if ($value !== '' && !preg_match($pattern, $value)) {
            $this->errors[$field] = $message;
        }
        return $this;
    }

    public function checked(string $field, string $label = ''): static
    {
        $label = $label ?: ucwords(str_replace('_', ' ', $field));
        $value = $this->data[$field] ?? '';
        if (!$value || $value === '0' || $value === 'false') {
            $this->errors[$field] = "You must agree to the {$label}.";
        }
        return $this;
    }

    public function min(string $field, float $min, string $label = ''): static
    {
        $label = $label ?: ucwords(str_replace('_', ' ', $field));
        $value = $this->data[$field] ?? '';
        if ($value !== '' && (float)$value < $min) {
            $this->errors[$field] = "{$label} must be at least {$min}.";
        }
        return $this;
    }

    public function max(string $field, float $max, string $label = ''): static
    {
        $label = $label ?: ucwords(str_replace('_', ' ', $field));
        $value = $this->data[$field] ?? '';
        if ($value !== '' && (float)$value > $max) {
            $this->errors[$field] = "{$label} must not exceed {$max}.";
        }
        return $this;
    }

    // Malaysian IC number: 12 digits, format YYMMDDXXXXXX
    public function malaysianIC(string $field): static
    {
        $value = preg_replace('/[^0-9]/', '', $this->data[$field] ?? '');
        if ($value !== '' && !preg_match('/^\d{12}$/', $value)) {
            $this->errors[$field] = 'IC / MyKad number must be 12 digits (without dashes).';
        } else {
            $this->data[$field] = $value; // normalize to digits-only
        }
        return $this;
    }

    // MyKad (12 digits, dashes/spaces allowed) or passport number (letters/digits as printed).
    public function icOrPassport(string $field): static
    {
        $raw = trim($this->data[$field] ?? '');
        if ($raw === '') {
            return $this;
        }
        $value = strtoupper(preg_replace('/[\s\-]/', '', $raw));
        $isMyKad    = preg_match('/^\d{12}$/', $value);
        $isPassport = preg_match('/^(?=.*[A-Z])[A-Z0-9]{6,20}$/', $value)  // letters + digits
                   || preg_match('/^\d{6,9}$/', $value);                   // all-digit passports
        if ($isMyKad || $isPassport) {
            $this->data[$field] = $value; // normalised: no dashes/spaces, upper case
        } else {
            $this->errors[$field] = 'Enter a 12-digit MyKad number (without dashes), or your passport number as printed (6–20 letters and digits).';
        }
        return $this;
    }

    public function phone(string $field, string $label = 'Phone number'): static
    {
        $value = $this->data[$field] ?? '';
        if ($value !== '' && !preg_match('/^[+]?[\d\s\-()]{8,20}$/', $value)) {
            $this->errors[$field] = "{$label} must be a valid phone number.";
        }
        return $this;
    }
}
