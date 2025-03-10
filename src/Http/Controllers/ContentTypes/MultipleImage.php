<?php

namespace TCG\Voyager\Http\Controllers\ContentTypes;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Constraint;
use Intervention\Image\Encoders\AutoEncoder;
use Intervention\Image\Laravel\Facades\Image as InterventionImage;

class MultipleImage extends BaseType
{
    /**
     * @return string
     */
    public function handle()
    {
        $filesPath = [];
        $files = $this->request->file($this->row->field);

        if (!$files) {
            return;
        }

        foreach ($files as $file) {
            if (!$file->isValid()) {
                continue;
            }

            $image = InterventionImage::read($file);

            $resize_width = null;
            $resize_height = null;

            if (isset($this->options->resize) && (
                isset($this->options->resize->width) || isset($this->options->resize->height)
            )) {
                if (isset($this->options->resize->width)) {
                    $resize_width = $this->options->resize->width;
                }
                if (isset($this->options->resize->height)) {
                    $resize_height = $this->options->resize->height;
                }
            } else {
                $resize_width = $image->width();
                $resize_height = $image->height();
            }

            $resize_quality = intval($this->options->quality ?? 75);

            $filename = Str::random(20);
            $path = $this->slug.DIRECTORY_SEPARATOR.date('FY').DIRECTORY_SEPARATOR;
            array_push($filesPath, $path.$filename.'.'.$file->getClientOriginalExtension());
            $filePath = $path.$filename.'.'.$file->getClientOriginalExtension();

            if (isset($this->options->upsize) && !$this->options->upsize) {
                $image = $image->scaleDown(
                    $resize_width,
                    $resize_height,
                )->encode(new AutoEncoder(quality: $resize_quality));
            } else {
                $image = $image->scale(
                    $resize_width,
                    $resize_height,
                )->encode(new AutoEncoder(quality: $resize_quality));
            }

            Storage::disk(config('voyager.storage.disk'))->put($filePath, (string) $image, 'public');

            if (isset($this->options->thumbnails)) {
                foreach ($this->options->thumbnails as $thumbnails) {
                    if (isset($thumbnails->name) && isset($thumbnails->scale)) {
                        $scale = intval($thumbnails->scale) / 100;
                        $thumb_resize_width = $resize_width;
                        $thumb_resize_height = $resize_height;

                        if ($thumb_resize_width != null && $thumb_resize_width != 'null') {
                            $thumb_resize_width = $thumb_resize_width * $scale;
                        }

                        if ($thumb_resize_height != null && $thumb_resize_height != 'null') {
                            $thumb_resize_height = $thumb_resize_height * $scale;
                        }

                        if (isset($this->options->upsize) && !$this->options->upsize) {
                            $image = InterventionImage::read($file)
                                ->scaleDown(
                                    $thumb_resize_width,
                                    $thumb_resize_height,
                                )->encode(new AutoEncoder(quality: $resize_quality));
                        } else {
                            $image = InterventionImage::read($file)
                                ->scale(
                                    $thumb_resize_width,
                                    $thumb_resize_height,
                                )->encode(new AutoEncoder(quality: $resize_quality));
                        }
                    } elseif (isset($this->options->thumbnails) && isset($thumbnails->crop->width) && isset($thumbnails->crop->height)) {
                        $crop_width = $thumbnails->crop->width;
                        $crop_height = $thumbnails->crop->height;
                        $image = InterventionImage::read($file)
                            ->cover($crop_width, $crop_height)
                            ->encode(new AutoEncoder(quality: $resize_quality));
                    }

                    Storage::disk(config('voyager.storage.disk'))->put(
                        $path.$filename.'-'.$thumbnails->name.'.'.$file->getClientOriginalExtension(),
                        (string) $image,
                        'public'
                    );
                }
            }
        }

        return json_encode($filesPath);
    }
}
