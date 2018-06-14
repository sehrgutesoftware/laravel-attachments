<?php

namespace Modules\Core\Domain\Models\Traits;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;

/* Please define this in your using Model:
protected $image_attachments = [
    'avatar' => [
        'path' => 'uploads/user/avatar', // partitioned object id + style will be appended, e.g. '/000/000/001/small/file.jpg'
        'styles' => [
            'small' => '100',
            'medium' => '500',
            'large' => '1000'
        ]
    ]
];

Original, unmanipulated image will be saved to folder 'original'
*/

trait HasImageAttachments
{
    /**
     * Returns an array containing all urls for the different sizes of a image
     * e.g. [
     *    'small' => 'http://www.project.test/uploads/avatar/000/000/001/small/image.jpg',
     *    'medium' => 'http://www.project.test/uploads/avatar/100/medium/image.jpg'
     *     ]
     *
     * @param String $attachment_name
     * @return array
     */
    protected function getImageAttachmentPaths(String $attachment_name)
    {
        if (!isset($this->attributes[$attachment_name])) {
            return $this->getDefaultImageAttachement($attachment_name);
        }

        $paths = [];
        foreach ($this->image_attachments[$attachment_name]['styles'] as $style => $value) {
            $path = $this->pathForStyle($style, $attachment_name) . $this->attributes[$attachment_name];
            $paths[$style] = Storage::disk('public')->url($path);
        }

        return $paths;
    }

    /**
     * Allows to define default styles as fallback for the frontend.
     *
     * @param $attachment_name
     * @return array | null
     */
    protected function getDefaultImageAttachement($attachment_name)
    {
        $attachment = $this->image_attachments[$attachment_name];

        if (!isset($attachment['defaults'])) {
            return null;
        }
        $paths = [];
        foreach ($attachment['styles'] as $style => $value) {
            if (!isset($attachment['defaults'][$style])) {
                continue;
            }

            $suffix = $attachment['defaults'][$style];
            $path = $attachment['path'] . '/defaults/' . $style . $suffix;

            $paths[$style] = Storage::disk('public')->url($path);
        }

        return $paths;
    }

    /**
     * Returns all defined style names and dimension for an attachment
     *
     * @param String $attachment_name
     * @return Array
     */
    public function getImageAttachmentStyles(
        String $attachment_name
    ) {
        return $this->image_attachments[$attachment_name]['styles'];
    }

    /**
     * - Saves a resized version of each defined style to disk
     * - Removes old files if existing
     * - Saves the filename to the database column
     *
     * @param String $attachment_name
     * @param String $content
     * @param String $type
     * @return Boolean
     */
    public function updateImageAttachment(
        String $attachment_name,
        String $content,
        String $type
    ) {
        if (!$this->validImageType($type)) {
            throw new \InvalidArgumentException('Invalid image type:' . $type);
        }

        $extension = $this->extractFileExtension($type);
        $filename = $this->generateFilename() . '.' . $extension;

        $old_filename = (isset($this->attributes[$attachment_name]) && $this->attributes[$attachment_name]) ? $this->attributes[$attachment_name] : null;

        foreach ($this->getImageAttachmentStyles($attachment_name) as $style => $width) {
            $path = $this->pathForStyle($style, $attachment_name);
            $img = $this->getResizedImageStream($content, $width);
            if ($this->saveImageAs($path . $filename, $img)) {
                if ($old_filename) {
                    $this->removeImage($path . $old_filename);
                }
            }
        }

        // Save original for later recalculation or something like that ...
        $original_path = $this->pathForStyle('original', $attachment_name);
        $this->saveImageAs($original_path . $filename, $content);
        if ($old_filename) {
            $this->removeImage($original_path . $old_filename);
        }

        $this->$attachment_name = $filename;

        return $this->save();
    }

    /**
     * Extracts image extension from Mime-Type
     *
     * @param String $type
     * @return String
     */
    protected function extractFileExtension(
        String $type
    ) {
        return (explode('/', $type))[1];
    }

    /**
     * Generates a unique string to use as filename (without extension)
     *
     * @return String
     */
    protected function generateFilename()
    {
        return md5($this->id . microtime());
    }

    /**
     * Checks if a string is a useful modern image Mime-Type
     *
     * @param String $type
     * @return Boolean
     */
    protected function validImageType(
        String $type
    ) {
        $valid = [
            'image/gif',
            'image/jpeg',
            'image/jpg', // Prevent erros
            'image/png',
            'image/tiff',
            'image/webp',
            'image/bmp',
        ];

        if (in_array($type, $valid)) {
            return true;
        }

        return false;
    }

    /**
     * Manipulate image dimensions of sent image with Imagine library:
     * Change width, keep ratio, no upscaling ...
     *
     * @param String $binary
     * @param Int $width
     * @return String
     */
    protected function getResizedImageStream(
        String $binary,
        Int $width
    ) {
        $img = Image::make($binary);
        $img->resize($width, null, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });

        return $img->stream();
    }

    /**
     * Saves a file to disk
     *
     * @param String $path The filepath including filename with extension
     * @param String $img
     * @return Boolean
     */
    protected function saveImageAs(
        String $filepath,
        String $img
    ) {
        return Storage::disk('public')->put($filepath, $img);
    }

    /**
     * Removes a file from disk
     *
     * @param String $filepath The filepath including filename with extension
     * @return Boolean
     */
    protected function removeImage(
        String $filepath
    ) {
        return Storage::disk('public')->delete($filepath);
    }

    protected function imageAttachmentBaseFolder(
        String $attachment_name
    ) {
        return $this->image_attachments[$attachment_name]['path'] . '/' . $this->partitionId($this->id) . '/';
    }

    /**
     * Full relative path to an attachment's directory for a given style, e.g. 'uploads/avatar/000/000/001/small/'
     *
     * @param String $style
     * @param String $attachment_name
     * @return String
     */
    protected function pathForStyle(
        String $style,
        String $attachment_name
    ) {
        $base_path = $this->imageAttachmentBaseFolder($attachment_name);

        return $base_path . $style . '/';
    }

    /**
     * Return a linux save folder structure even for long ids.
     *
     * @param int $id
     * @return string
     */
    public static function partitionId(
        int $id
    ): string {
        return implode('/', str_split(sprintf('%09d', $id), 3));
    }

    /**
     * Remove an existing image attachment from db and physically.
     *
     * @param String $attachment_name
     * @return bool
     */
    public function removeImageAttachment(
        String $attachment_name
    ): bool {
        $this->$attachment_name = null;
        $this->save();

        return Storage::disk('public')->deleteDirectory($this->imageAttachmentBaseFolder($attachment_name));
    }
}
