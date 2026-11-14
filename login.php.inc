<?php

declare(strict_types=1);

class loginDB
{
    private mysqli $logindb;

    public function __construct()
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $configPath = __DIR__ . '/config/private/database.ini';
        $config = @parse_ini_file(
            $configPath,
            false,
            INI_SCANNER_RAW
        );

        if (!is_array($config)) {
            throw new RuntimeException(
                'The private database configuration could not be loaded.'
            );
        }

        foreach (['user', 'password', 'database'] as $key) {
            if (!is_string($config[$key] ?? null)
                || $config[$key] === '') {
                throw new RuntimeException(
                    'The private database configuration is incomplete.'
                );
            }
        }

        $this->logindb = new mysqli(
            '127.0.0.1',
            $config['user'],
            $config['password'],
            $config['database']
        );

        $this->logindb->set_charset('utf8mb4');
    }

    public function validateLogin($username, $password): array
    {
        if (!is_string($username)
            || !is_string($password)
            || trim($username) === ''
            || $password === '') {
            return [
                'ok' => false,
                'message' => 'Invalid username or password.'
            ];
        }

        $username = trim($username);

        try {
            $stmt = $this->logindb->prepare(
                'SELECT id, password
                 FROM users
                 WHERE screenname = ?
                 LIMIT 1'
            );

            $stmt->bind_param('s', $username);
            $stmt->execute();

            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$user
                || !password_verify($password, $user['password'])) {
                return [
                    'ok' => false,
                    'message' => 'Invalid username or password.'
                ];
            }

            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $userId = (int) $user['id'];

            $stmt = $this->logindb->prepare(
                'INSERT INTO sessions
                    (user_id, token_hash, last_used_at, expires_at)
                 VALUES (
                    ?,
                    ?,
                    NOW(),
                    DATE_ADD(NOW(), INTERVAL 30 MINUTE)
                 )'
            );

            $stmt->bind_param('is', $userId, $tokenHash);
            $stmt->execute();
            $stmt->close();

            return [
                'ok' => true,
                'token' => $token
            ];
        } catch (Throwable $error) {
            return $this->serverError('login', $error);
        }
    }

    public function registerUser(
        $name,
        $username,
        $email,
        $password
    ): array {
        if (!is_string($name)
            || !is_string($username)
            || !is_string($email)
            || !is_string($password)) {
            return [
                'ok' => false,
                'message' => 'Invalid registration details.'
            ];
        }

        $name = trim($name);
        $username = strtolower(trim($username));
        $email = strtolower(trim($email));

        // No username character pattern or minimum length.
        if ($username === '') {
            return [
                'ok' => false,
                'message' => 'Enter a username.'
            ];
        }

        // Basic email check: something before and after @.
        if (strlen($email) > 254
            || !preg_match('/^.+@.+$/', $email)) {
            return [
                'ok' => false,
                'message' => 'Enter an email like name@something.'
            ];
        }

        // Five-character minimum; retain the hashing limits.
        if (!preg_match('/\A.{5,}\z/us', $password)
            || strlen($password) > 72
            || str_contains($password, "\0")) {
            return [
                'ok' => false,
                'message' => 'Use at least 5 characters, up to 72 bytes.'
            ];
        }

        if (strlen($name) > 100) {
            return [
                'ok' => false,
                'message' => 'The supplied name is too long.'
            ];
        }

        try {
            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = $this->logindb->prepare(
                'INSERT INTO users (name, screenname, email, password)
                 VALUES (?, ?, ?, ?)'
            );

            $stmt->bind_param(
                'ssss',
                $name,
                $username,
                $email,
                $passwordHash
            );

            $stmt->execute();
            $stmt->close();

            return [
                'ok' => true,
                'message' => 'Account created. You can now log in.'
            ];
        } catch (Throwable $error) {
            if ($error instanceof mysqli_sql_exception
                && (int) $error->getCode() === 1062) {
                return [
                    'ok' => false,
                    'message' => 'That username or email is already in use.'
                ];
            }

            return $this->serverError('register', $error);
        }
    }

    public function validateSession($token): array
    {
        if (!is_string($token)
            || !preg_match('/\A[a-f0-9]{64}\z/', $token)) {
            return [
                'ok' => false,
                'error' => 'unauthorized'
            ];
        }

        try {
            $tokenHash = hash('sha256', $token);

            $stmt = $this->logindb->prepare(
                'SELECT s.user_id, u.screenname
                 FROM sessions AS s
                 JOIN users AS u ON u.id = s.user_id
                 WHERE s.token_hash = ?
                   AND s.revoked_at IS NULL
                   AND s.expires_at > NOW()
                 LIMIT 1'
            );

            $stmt->bind_param('s', $tokenHash);
            $stmt->execute();

            $session = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$session) {
                return [
                    'ok' => false,
                    'error' => 'unauthorized'
                ];
            }

            $stmt = $this->logindb->prepare(
                'UPDATE sessions
                 SET last_used_at = NOW()
                 WHERE token_hash = ?
                   AND revoked_at IS NULL
                   AND expires_at > NOW()'
            );

            $stmt->bind_param('s', $tokenHash);
            $stmt->execute();
            $stmt->close();

            return [
                'ok' => true,
                'user_id' => (int) $session['user_id'],
                'username' => $session['screenname']
            ];
        } catch (Throwable $error) {
            return $this->serverError('validate_session', $error);
        }
    }

    public function logoutSession($token): array
    {
        if (!is_string($token)
            || !preg_match('/\A[a-f0-9]{64}\z/', $token)) {
            return ['ok' => true];
        }

        try {
            $tokenHash = hash('sha256', $token);

            $stmt = $this->logindb->prepare(
                'UPDATE sessions
                 SET revoked_at = NOW()
                 WHERE token_hash = ?
                   AND revoked_at IS NULL'
            );

            $stmt->bind_param('s', $tokenHash);
            $stmt->execute();
            $stmt->close();

            return ['ok' => true];
        } catch (Throwable $error) {
            return $this->serverError('logout', $error);
        }
    }

    public function getJobs($token): array
    {
        try {
            $session = $this->validateSession($token);

            if (($session['ok'] ?? false) !== true) {
                return $session;
            }

            $result = $this->logindb->query(
                'SELECT job_id, data, collected_at
                 FROM applyweek_jobs
                 ORDER BY collected_at DESC, job_id DESC
                 LIMIT 50'
            );

            $jobs = [];

            while ($row = $result->fetch_assoc()) {
                $data = json_decode(
                    $row['data'],
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

                if (!is_array($data)) {
                    throw new RuntimeException(
                        'Invalid stored job data.'
                    );
                }

                $jobs[] = [
                    'job_id' => $row['job_id'],
                    'title' => $data['business_title'] ?? 'Untitled',
                    'agency' => $data['agency'] ?? 'Not listed',
                    'career_level' => $data['career_level'] ?? 'Not listed',
                    'salary_from' => $data['salary_range_from'] ?? null,
                    'salary_to' => $data['salary_range_to'] ?? null,
                    'salary_frequency' => $data['salary_frequency'] ?? '',
                    'collected_at' => $row['collected_at']
                ];
            }

            $result->free();

            return [
                'ok' => true,
                'username' => $session['username'],
                'jobs' => $jobs
            ];
        } catch (Throwable $error) {
            return $this->serverError('get_jobs', $error);
        }
    }

    public function importJobs($jobs): array
    {
        if (!is_array($jobs)
            || !array_is_list($jobs)
            || count($jobs) < 1
            || count($jobs) > 100) {
            return [
                'ok' => false,
                'error' => 'invalid_request',
                'message' => 'Invalid import batch.'
            ];
        }

        $inTransaction = false;

        try {
            $records = [];

            foreach ($jobs as $job) {
                if (!is_array($job)) {
                    return [
                        'ok' => false,
                        'error' => 'invalid_request',
                        'message' => 'Invalid job in import batch.'
                    ];
                }

                $jobId = $job['job_id'] ?? null;

                if ((!is_string($jobId) && !is_int($jobId))
                    || !preg_match(
                        '/\A[0-9]{1,32}\z/',
                        (string) $jobId
                    )
                    || ($job['posting_type'] ?? '') !== 'External'
                    || !in_array(
                        $job['career_level'] ?? '',
                        ['Student', 'Entry-Level'],
                        true
                    )
                    || !is_string($job['business_title'] ?? null)) {
                    return [
                        'ok' => false,
                        'error' => 'invalid_request',
                        'message' => 'Invalid job in import batch.'
                    ];
                }

                $records[(string) $jobId] = json_encode(
                    $job,
                    JSON_THROW_ON_ERROR
                );
            }

            $stmt = $this->logindb->prepare(
                'INSERT INTO applyweek_jobs (job_id, data, collected_at)
                 VALUES (?, ?, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE
                     data = ?,
                     collected_at = UTC_TIMESTAMP()'
            );

            $this->logindb->begin_transaction();
            $inTransaction = true;

            foreach ($records as $jobId => $json) {
                $id = (string) $jobId;

                $stmt->bind_param('sss', $id, $json, $json);
                $stmt->execute();
            }

            $this->logindb->commit();
            $inTransaction = false;
            $stmt->close();

            return [
                'ok' => true,
                'processed' => count($records)
            ];
        } catch (Throwable $error) {
            if ($inTransaction) {
                try {
                    $this->logindb->rollback();
                } catch (Throwable $rollbackError) {
                    error_log(
                        'ApplyWeek job import rollback failed.'
                    );
                }
            }

            return $this->serverError('import_jobs', $error);
        }
    }

    private function serverError(
        string $operation,
        Throwable $error
    ): array {
        error_log(
            'ApplyWeek ' . $operation . ' failed: '
            . get_class($error)
            . ' code '
            . $error->getCode()
        );

        return [
            'ok' => false,
            'error' => 'server_error',
            'message' => 'The service is temporarily unavailable.'
        ];
    }
}
