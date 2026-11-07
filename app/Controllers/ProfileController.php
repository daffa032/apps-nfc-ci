<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\FieldException;
use App\Services\Cards\BrandAssetService;
use App\Services\Cards\GalleryService;
use App\Services\Cards\ProfileService;
use App\Services\Cards\PublicCardService;
use App\Services\Cards\SocialLinkService;
use App\Services\Cards\TemplateSelectionService;
use App\Traits\RendersCardPages;
use App\Traits\ResolvesCurrentUser;
use App\Traits\RespondsWithFeedback;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Throwable;

class ProfileController extends BaseController
{
    use RendersCardPages;
    use ResolvesCurrentUser;
    use RespondsWithFeedback;

    private const PROFILE_PAGE = 'dashboard/personal_info_page';

    protected $helpers = ['asset', 'feedback'];

    public function addProfileForm(): string
    {
        return view('dashboard/pages/personal_info/personal_info_form', $this->dashboardShellData() + [
            'feedback'    => $this->pendingFeedback(),
            'fieldErrors' => $this->pendingFieldErrors(),
        ]);
    }

    public function personalInfo(): RedirectResponse|string
    {
        $page = (new ProfileService())->pageFor($this->currentUserId());
        if ($page === null) {
            return $this->redirectFailure('dashboard/personal_info_form', lang('Profile.needProfile'));
        }

        return view('dashboard/pages/personal_info/personal_info_page', $this->dashboardShellData() + $page + [
            'brand'       => (new BrandAssetService())->pageFor($this->currentUserId(), new DateTimeImmutable('now')),
            'isEdit'      => true,
            'feedback'    => $this->pendingFeedback(),
            'fieldErrors' => $this->pendingFieldErrors(),
        ]);
    }

    /**
     * The owner's card in their current design for the live preview (PRD PV-01). The page applies
     * unsaved form values on top in the browser; nothing here is counted as a visit or cached.
     */
    public function preview(): string
    {
        $card = (new TemplateSelectionService())->currentPreviewFor($this->currentUserId(), new PublicCardService());
        if ($card === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $this->response
            ->setHeader('Cache-Control', 'private, no-store')
            ->setHeader('X-Frame-Options', 'SAMEORIGIN')
            ->setHeader('X-Robots-Tag', 'noindex');

        return $this->renderCardPage($card['view'], $card['data']);
    }

    /**
     * Brand logo / header background for the current Pro design (PRD BR-01).
     */
    public function saveBrandAssets(): RedirectResponse
    {
        $files = [];

        foreach (['brand_logo', 'header_background'] as $slot) {
            $files[$slot] = $this->request->getFile($slot);
        }
        $remove = array_values(array_intersect((array) $this->request->getPost('remove'), ['brand_logo', 'header_background']));
        // Each switch posts a hidden "0" followed by the checkbox "1"; a slot without a switch is left as is.
        $visible = [];

        foreach ((array) $this->request->getPost('visible') as $slot => $value) {
            if (in_array($slot, ['brand_logo', 'header_background'], true)) {
                $visible[$slot] = (string) $value === '1';
            }
        }

        try {
            $changed = (new BrandAssetService())->save($this->currentUserId(), $files, $remove, new DateTimeImmutable('now'), $visible);
        } catch (FieldException $exception) {
            $this->audit('brand.updated', 'denied', 'profile', $this->currentUserId(), [$exception->field]);

            return $this->redirectFieldFailure(self::PROFILE_PAGE, $exception);
        } catch (Throwable $exception) {
            log_message('error', 'Brand asset save failed: {exception}', ['exception' => $exception]);
            $this->audit('brand.updated', 'failed', 'profile', $this->currentUserId(), [], 'server_error');

            return $this->redirectFailure(self::PROFILE_PAGE, $this->withReference(lang('Profile.brandFailed')));
        }
        if ($changed === []) {
            return $this->redirectFailure(self::PROFILE_PAGE, lang('Profile.brandNothing'));
        }

        $this->audit('brand.updated', 'success', 'profile', $this->currentUserId(), $changed);

        return $this->redirectSuccess(self::PROFILE_PAGE, lang('Profile.brandSaved'));
    }

    public function addProfile(): RedirectResponse
    {
        return $this->saveProfile();
    }

    public function updateProfile(): RedirectResponse
    {
        return $this->saveProfile();
    }

    public function uploadMultipleImages(): RedirectResponse
    {
        try {
            (new GalleryService())->upload(
                $this->currentUserId(),
                $this->request->getFiles()['images'] ?? [],
                (array) $this->request->getPost('captions'),
            );
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->redirectFailure(self::PROFILE_PAGE, $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Gallery upload failed: {exception}', ['exception' => $exception]);
            $this->audit('gallery.uploaded', 'failed', 'profile', $this->currentUserId(), [], 'server_error');

            return $this->redirectFailure(self::PROFILE_PAGE, $this->withReference(lang('Profile.uploadFailed')));
        }

        $this->audit('gallery.uploaded', 'success', 'profile', $this->currentUserId(), ['images']);

        return $this->redirectSuccess(self::PROFILE_PAGE, lang('Profile.uploaded'));
    }

    public function saveCardLanguage(): RedirectResponse
    {
        $locale = $this->request->getPost('card_locale');

        try {
            (new ProfileService())->setCardLocale($this->currentUserId(), is_string($locale) ? $locale : '');
        } catch (FieldException $exception) {
            return $this->redirectFieldFailure(self::PROFILE_PAGE, $exception);
        } catch (DomainException $exception) {
            return $this->redirectFailure(self::PROFILE_PAGE, $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Card language save failed: {exception}', ['exception' => $exception]);
            $this->audit('profile.updated', 'failed', 'profile', $this->currentUserId(), ['card_locale'], 'server_error');

            return $this->redirectFailure(self::PROFILE_PAGE, $this->withReference(lang('Profile.cardLanguageFailed')));
        }

        $this->audit('profile.updated', 'success', 'profile', $this->currentUserId(), ['card_locale']);

        return $this->redirectSuccess(self::PROFILE_PAGE, lang('Profile.cardLanguageSaved'));
    }

    public function saveGalleryLayout(): RedirectResponse
    {
        try {
            (new GalleryService())->setColumns($this->currentUserId(), (int) $this->request->getPost('gallery_columns'));
        } catch (DomainException $exception) {
            return $this->redirectFailure(self::PROFILE_PAGE, $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Gallery layout save failed: {exception}', ['exception' => $exception]);

            return $this->redirectFailure(self::PROFILE_PAGE, $this->withReference(lang('Profile.layoutFailed')));
        }

        $this->audit('gallery.layout_updated', 'success', 'profile', $this->currentUserId(), ['gallery_columns']);

        return $this->redirectSuccess(self::PROFILE_PAGE, lang('Profile.layoutSaved'));
    }

    public function updateCaption(): ResponseInterface
    {
        try {
            (new GalleryService())->updateCaption(
                $this->currentUserId(),
                (int) $this->request->getPost('id'),
                (string) $this->request->getPost('caption'),
            );
        } catch (DomainException $exception) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            log_message('error', 'Gallery caption update failed: {exception}', ['exception' => $exception]);

            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => $this->withReference(lang('Profile.captionFailed'))]);
        }

        $this->audit('gallery.caption_updated', 'success', 'image', (int) $this->request->getPost('id'), ['caption']);

        return $this->response->setJSON(['success' => true, 'message' => lang('Profile.captionSaved')]);
    }

    public function deleteMultipleImages(): ResponseInterface
    {
        try {
            (new GalleryService())->delete($this->currentUserId(), (int) $this->request->getPost('id'));
        } catch (DomainException $exception) {
            $this->audit('gallery.deleted', 'denied', 'image', (int) $this->request->getPost('id'), [], 'not_owner_or_missing');

            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            log_message('error', 'Gallery deletion failed: {exception}', ['exception' => $exception]);

            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => $this->withReference(lang('Profile.photoDeleteFailed'))]);
        }

        $this->audit('gallery.deleted', 'success', 'image', (int) $this->request->getPost('id'));

        return $this->response->setJSON(['success' => true, 'message' => lang('Profile.photoDeleted')]);
    }

    public function saveSocialMedia(): ResponseInterface
    {
        try {
            (new SocialLinkService())->save(
                $this->currentUserId(),
                (string) $this->request->getPost('type'),
                (string) $this->request->getPost('link'),
            );
            // Channel name only; the URL itself is never written to the audit log.
            $this->audit('social.updated', 'success', 'profile', $this->currentUserId(), [(string) $this->request->getPost('type')]);
            // The page reloads after this response; the flash shows the result toast there.
            session()->setFlashdata('success', lang('Profile.linkSaved'));

            return $this->response->setJSON(['status' => 'success', 'message' => lang('Profile.linkSaved')]);
        } catch (DomainException $exception) {
            return $this->response->setStatusCode(422)->setJSON(['status' => 'error', 'message' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            log_message('error', 'Social link save failed: {exception}', ['exception' => $exception]);

            return $this->response->setStatusCode(500)->setJSON(['status' => 'error', 'message' => lang('Profile.linkSaveFailed')]);
        }
    }

    public function deleteSocialMedia(): ResponseInterface
    {
        try {
            (new SocialLinkService())->delete(
                $this->currentUserId(),
                (string) $this->request->getPost('socialMediaType'),
            );
            $this->audit('social.updated', 'success', 'profile', $this->currentUserId(), [(string) $this->request->getPost('socialMediaType')], 'removed');
            session()->setFlashdata('success', lang('Profile.linkDeleted'));

            return $this->response->setJSON(['status' => 'success', 'message' => lang('Profile.linkDeleted')]);
        } catch (DomainException $exception) {
            return $this->response->setStatusCode(422)->setJSON(['status' => 'error', 'message' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            log_message('error', 'Social link deletion failed: {exception}', ['exception' => $exception]);

            return $this->response->setStatusCode(500)->setJSON(['status' => 'error', 'message' => lang('Profile.linkDeleteFailed')]);
        }
    }

    private function saveProfile(): RedirectResponse
    {
        try {
            (new ProfileService())->save(
                $this->currentUserId(),
                (array) $this->request->getPost(),
                $this->request->getFile('profile_image'),
            );
        } catch (FieldException $exception) {
            session()->setFlashdata('field_errors', [$exception->field => $exception->getMessage()]);

            return $this->backWithFailure($exception->getMessage());
        } catch (DomainException|InvalidArgumentException $exception) {
            // Upload problems (type, size, dimensions) belong to the photo input.
            session()->setFlashdata('field_errors', ['profile_image' => $exception->getMessage()]);

            return $this->backWithFailure($exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Profile save failed: {exception}', ['exception' => $exception]);
            $this->audit('profile.updated', 'failed', 'profile', $this->currentUserId(), [], 'server_error');

            return $this->backWithFailure($this->withReference(lang('Profile.saveFailed')));
        }

        $fields = ['nama', 'company_name', 'job_title', 'no_hp', 'no_whatsapp', 'email', 'address', 'google_map_link'];
        if (($file = $this->request->getFile('profile_image')) && $file->getError() !== UPLOAD_ERR_NO_FILE) {
            $fields[] = 'profile_image';
        }
        $this->audit('profile.updated', 'success', 'profile', $this->currentUserId(), $fields);

        return $this->redirectSuccess(self::PROFILE_PAGE, lang('Profile.saved'));
    }
}
