<?php

declare(strict_types=1);

namespace App\Handlers;

use PDO;
use Exception;
use App\Services\MonologHandler;
use App\Services\EmailVerificationService;
use App\Exceptions\NotFoundException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * EmailVerificationHandler
 *
 * Thin HTTP wrapper — all business logic lives in EmailVerificationService.
 */
class EmailVerificationHandler extends BaseHandler
{
    private function emailVerificationService(): EmailVerificationService
    {
        return $this->services->emailVerification();
    }

    // =========================================================
    // Send verification email
    // =========================================================

    public function sendVerificationEmail(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            if (!is_array($data)) {
                return $this->errorResponse($response, 'Invalid request data', 400);
            }

            $email   = trim((string) ($data['email'] ?? ''));
            $purpose = (string) ($data['purpose'] ?? 'registration');

            if ($email === '') {
                return $this->errorResponse($response, 'البريد الإلكتروني مطلوب', 400);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->errorResponse($response, 'تنسيق البريد الإلكتروني غير صالح', 400);
            }

            $result = $this->emailVerificationService()->sendVerification($email, $purpose, $request);

            return $this->successResponse($response, array_merge(
                ['message' => 'Verification email sent'],
                $result
            ));
        } catch (NotFoundException $e) {
            return $this->errorResponse($response, $e->getMessage(), 404);
        } catch (Exception $e) {
            $this->logger->error('sendVerificationEmail failed', ['message' => $e->getMessage()]);
            $msg = $e->getMessage();
            if (str_contains($msg, 'Rate limit')) {
                return $this->errorResponse($response, 'Too many requests', 429);
            }
            return $this->errorResponse($response, 'Failed to send verification email', 500);
        }
    }

    // =========================================================
    // Verify email token
    // =========================================================

    public function verifyEmail(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            if (!is_array($data)) {
                return $this->errorResponse($response, 'Invalid request data', 400);
            }

            $token = trim((string) ($data['token'] ?? ''));
            if ($token === '') {
                return $this->errorResponse($response, 'Verification token is required', 400);
            }

            $result = $this->emailVerificationService()->verifyToken($token);

            // Auto-login: generate access token for registration/email_change
            $accessToken = null;
            if (
                in_array($result['purpose'] ?? '', ['registration', 'email_change'], true)
                && !empty($result['user'])
            ) {
                try {
                    // Use container to get AuthHandler with all dependencies resolved
                    global $container;
                    if ($container && $container->has(\App\Handlers\AuthHandler::class)) {
                        $authHandler = $container->get(\App\Handlers\AuthHandler::class);
                    } else {
                        $authHandler = new \App\Handlers\AuthHandler($this->db);
                    }
                    $accessToken = $authHandler->generateAccessTokenPublic($result['user']);
                } catch (\Throwable $e) {
                    $this->logger->warning('verifyEmail: failed to generate auto-login token', [
                        'user_id' => $result['user_id'] ?? null,
                        'error'   => $e->getMessage(),
                    ]);
                }
            }

            return $this->jsonResponse($response, [
                'status'       => 'success',
                'message'      => 'Email verified successfully',
                'data'         => $result,
                'access_token' => $accessToken,
            ], 200, false);
        } catch (Exception $e) {
            $msg = $e->getMessage();
            $this->logger->error('verifyEmail failed', ['message' => $msg]);
            $code = str_contains($msg, 'Too many') ? 429 : 400;
            return $this->errorResponse($response, $msg, $code);
        }
    }

    // =========================================================
    // Resend verification email
    // =========================================================

    public function resendVerificationEmail(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            if (!is_array($data)) {
                return $this->errorResponse($response, 'Invalid request data', 400);
            }

            $email   = trim((string) ($data['email'] ?? ''));
            $purpose = (string) ($data['purpose'] ?? 'registration');

            if ($email === '') {
                return $this->errorResponse($response, 'البريد الإلكتروني مطلوب', 400);
            }

            $result = $this->emailVerificationService()->resend($email, $purpose, $request);

            return $this->successResponse($response, array_merge(
                ['message' => 'Verification email sent'],
                $result
            ));
        } catch (NotFoundException $e) {
            return $this->errorResponse($response, $e->getMessage(), 404);
        } catch (Exception $e) {
            $this->logger->error('resendVerificationEmail failed', ['message' => $e->getMessage()]);
            return $this->errorResponse($response, 'Failed to resend verification email', 500);
        }
    }

    // =========================================================
    // Check verification status
    // =========================================================

    public function checkVerificationStatus(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            if (!is_array($data)) {
                return $this->errorResponse($response, 'Invalid request data', 400);
            }

            $email = trim((string) ($data['email'] ?? ''));
            if ($email === '') {
                return $this->errorResponse($response, 'البريد الإلكتروني مطلوب', 400);
            }

            $status = $this->emailVerificationService()->getStatus($email);

            return $this->successResponse($response, ['data' => $status]);
        } catch (NotFoundException $e) {
            return $this->errorResponse($response, $e->getMessage(), 404);
        } catch (Exception $e) {
            $this->logger->error('checkVerificationStatus failed', ['message' => $e->getMessage()]);
            return $this->errorResponse($response, 'Failed to check verification status', 500);
        }
    }

    // =========================================================
    // Forgot password
    // =========================================================

    public function forgotPassword(Request $request, Response $response): Response
    {
        // رسالة عامة دائماً — لا نكشف وجود الحساب
        $genericMsg = ['message' => 'إذا كان الإيميل مسجلاً، ستصلك رسالة خلال دقائق'];

        try {
            $data = $request->getParsedBody();
            if (!is_array($data)) {
                return $this->successResponse($response, $genericMsg);
            }

            $email = trim((string) ($data['email'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->successResponse($response, $genericMsg);
            }

            $this->emailVerificationService()->sendPasswordReset($email, $request);

            return $this->successResponse($response, $genericMsg);
        } catch (Exception $e) {
            $this->logger->error('forgotPassword failed', ['message' => $e->getMessage()]);
            return $this->successResponse($response, $genericMsg);
        }
    }

    // =========================================================
    // Reset password
    // =========================================================

    public function resetPassword(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            if (!is_array($data)) {
                return $this->errorResponse($response, 'Invalid request data', 400);
            }

            $token       = trim((string) ($data['token'] ?? ''));
            $newPassword = (string) ($data['new_password'] ?? '');

            if ($token === '' || $newPassword === '') {
                return $this->errorResponse($response, 'بيانات غير مكتملة', 400);
            }

            $this->emailVerificationService()->resetPassword($token, $newPassword);

            return $this->jsonResponse($response, [
                'status'  => 'success',
                'message' => 'تم تحديث كلمة المرور بنجاح',
                'data'    => null,
            ], 200, false);
        } catch (Exception $e) {
            $this->logger->error('resetPassword failed', ['message' => $e->getMessage()]);
            return $this->errorResponse($response, $e->getMessage() ?: 'فشل تحديث كلمة المرور', 400);
        }
    }
}
