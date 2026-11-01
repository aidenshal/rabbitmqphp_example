<?php

class loginDB
{
    private $logindb;

    public function __construct()
    {
        $this->logindb = new mysqli("127.0.0.1", "it490_app", "12345", "login");

        if ($this->logindb->connect_errno != 0) {
            echo "Error connecting to database: "
                . $this->logindb->connect_error . PHP_EOL;
            exit(1);
        }

        echo "correctly connected to database" . PHP_EOL;
    }

    public function validateLogin($username, $password)
    {
        $un = $this->logindb->real_escape_string($username);
        $pw = $this->logindb->real_escape_string($password);

        $statement = "SELECT * FROM users WHERE screenname = '$un'";
        $response = $this->logindb->query($statement);

        while ($row = $response->fetch_assoc()) {
            echo "checking password for $username" . PHP_EOL;

            if ($row["password"] == $pw) {
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $userId = (int) $row['id'];

                $stmt = $this->logindb->prepare(
                    "INSERT INTO sessions
                        (user_id, token_hash, last_used_at, expires_at)
                     VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 MINUTE))"
                );

                $stmt->bind_param("is", $userId, $tokenHash);
                $stmt->execute();
                $stmt->close();

                return [
                    'ok' => true,
                    'token' => $token
                ];
            }

            echo "passwords did not match for $username" . PHP_EOL;
        }

        return 0;
    }

    public function validateSession($token)
    {
        if (!is_string($token)
            || !preg_match('/\A[a-f0-9]{64}\z/', $token)) {
            return ['ok' => false];
        }

        $tokenHash = hash('sha256', $token);

        $stmt = $this->logindb->prepare(
            "SELECT s.user_id, u.screenname
             FROM sessions s
             JOIN users u ON u.id = s.user_id
             WHERE s.token_hash = ?
               AND s.revoked_at IS NULL
               AND s.expires_at > NOW()"
        );

        $stmt->bind_param("s", $tokenHash);
        $stmt->execute();
        $session = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$session) {
            return ['ok' => false];
        }

        $stmt = $this->logindb->prepare(
            "UPDATE sessions
             SET last_used_at = NOW()
             WHERE token_hash = ?
               AND revoked_at IS NULL
               AND expires_at > NOW()"
        );

        $stmt->bind_param("s", $tokenHash);
        $stmt->execute();
        $stmt->close();

        return [
            'ok' => true,
            'user_id' => (int) $session['user_id'],
            'username' => $session['screenname']
        ];
    }

    public function logoutSession($token)
    {
        if (!is_string($token)
            || !preg_match('/\A[a-f0-9]{64}\z/', $token)) {
            return ['ok' => true];
        }

        $tokenHash = hash('sha256', $token);

        $stmt = $this->logindb->prepare(
            "UPDATE sessions
             SET revoked_at = NOW()
             WHERE token_hash = ?
               AND revoked_at IS NULL"
        );

        $stmt->bind_param("s", $tokenHash);
        $stmt->execute();
        $stmt->close();

        return ['ok' => true];
    }

    public function importJobs($jobs)
    {
        if (!is_array($jobs)
            || !array_is_list($jobs)
            || count($jobs) < 1
            || count($jobs) > 100) {
            return ['ok' => false, 'message' => 'Invalid import batch.'];
        }

        $inTransaction = false;

        try {
            $records = [];

            foreach ($jobs as $job) {
                if (!is_array($job)) {
                    throw new RuntimeException('Invalid job in import batch.');
                }

                $jobId = $job['job_id'] ?? null;

                if ((!is_string($jobId) && !is_int($jobId))
                    || !preg_match('/\A[0-9]{1,32}\z/', (string) $jobId)
                    || ($job['posting_type'] ?? '') !== 'External'
                    || !in_array(
                        $job['career_level'] ?? '',
                        ['Student', 'Entry-Level'],
                        true
                    )
                    || !is_string($job['business_title'] ?? null)) {
                    throw new RuntimeException('Invalid job in import batch.');
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
                    error_log('ApplyWeek rollback failed.');
                }
            }

            error_log('ApplyWeek import failed: ' . $error->getMessage());

            return [
                'ok' => false,
                'message' => 'Job import failed.'
            ];
        }
    }

    public function registerUser($name, $username, $email, $password)
    {
        $nm = $this->logindb->real_escape_string($name);
        $un = $this->logindb->real_escape_string($username);
        $em = $this->logindb->real_escape_string($email);
        $pw = $this->logindb->real_escape_string($password);

        $checkStmt = "SELECT * FROM users WHERE screenname = '$un'";
        $result = $this->logindb->query($checkStmt);

        if ($result && $result->num_rows > 0) {
            echo "Registration failed: Username '$username' already exists."
                . PHP_EOL;
            return 0;
        }

        $insertStmt = "INSERT INTO users (name, screenname, email, password)
                       VALUES ('$nm', '$un', '$em', '$pw')";

        if ($this->logindb->query($insertStmt)) {
            echo "Successfully registered new user: $username" . PHP_EOL;
            return 1;
        }

        echo "Error inserting user into database." . PHP_EOL;
        return 0;
    }
}
