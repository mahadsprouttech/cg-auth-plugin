<?php

class CgYoutubeData
{
    public function getCaptions($video_id)
    {
        $captions = $this->getCaptionsList($video_id);
        if (is_wp_error($captions)) {
            return [
                'success'   => false,
                'data'      => [
                    'code'      => $captions->get_error_code(),
                    'message'   => $captions->get_error_message()
                ]
            ];
        }

        return [
            'success'   => true,
            'data'      => $captions
        ];
    }

    public function getCaption($video_id, $lang)
    {
        $captions = $this->getCaptionsList($video_id);
        if (is_wp_error($captions))
        {
            return [
                'success'   => false,
                'data'      => [
                    'code'      => $captions->get_error_code(),
                    'message'   => $captions->get_error_message()
                ]
            ];
        }

        $caption_url = '';
        foreach ($captions['translationLanguages'] as $language)
        {
            if($language['languageCode'] == $lang)
            {
                $caption_url = $language['url'];
                break;
            }
        }

        if (empty($caption_url)) {
            return [
                'success'   => false,
                'data'      => [
                    'code'      => 'lang_not_exists',
                    'message'   => __('Requested language does not exists')
                ]
            ];
        }

        $request = wp_remote_get($caption_url, array('timeout' => 120));
        if (is_wp_error($request)) {
            return [
                'success'   => false,
                'data'      => [
                    'code'      => 'youtube_api_error',
                    'message'   => $request->get_error_message()
                ]
            ];
        }

        if (200 !== $request['response']['code']) {
            if (429 == $request['response']['code']) {
                return [
                    'success'   => false,
                    'data'      => [
                        'code'      => 'youtube_api_limit_reached',
                        'message'   => __('Youtube api limit reached')
                    ]
                ];
            } else {
                return [
                    'success'   => false,
                    'data'      => [
                        'code'      => 'youtube_api_unknown_response',
                        'message'   => $request['response']['message']
                    ]
                ];
            }
        }

        $xml_string = $request['body'];
        $captions_array = simplexml_load_string($xml_string);

        $captions = '';
        foreach ($captions_array as $line)
        {
            if (! is_array($line))
            {
                $captions .= ' '. $line;
            }
        }

        $data = $this->convertToParagraphs($captions);
        return [
            'success'   => true,
            'data'      => $data
        ];
    }

    public function getCaptionsList($video_id)
    {
 
	$body = '{  "context": {    "client": {      "hl": "en",      "clientName": "WEB",      "clientVersion": "2.20210721.00.00",      "clientFormFactor": "UNKNOWN_FORM_FACTOR",   "clientScreen": "WATCH",      "mainAppWebInfo": {        "graftUrl": "/watch?v='.$video_id.'",           }    },    "user": {      "lockedSafetyMode": false    },    "request": {      "useSsl": true,      "internalExperimentFlags": [],      "consistencyTokenJars": []    }  },  "videoId": "'.$video_id.'",  "playbackContext": {    "contentPlaybackContext": {        "vis": 0,      "splay": false,      "autoCaptionsDefaultOn": false,      "autonavState": "STATE_NONE",      "html5Preference": "HTML5_PREF_WANTS",      "lactMilliseconds": "-1"    }  },  "racyCheckOk": false,  "contentCheckOk": false}';
	$options = [
	    'body'        => $body,
	    'headers'     => [
		'Content-Type' => 'application/json',
		'Accept-Encoding' => 'gzip, deflate',
	    ],
	    'timeout'     => 60,
	    'sslverify'   => false,
	    'data_format' => 'body'
	];
 
        $request = wp_remote_post('https://www.youtube.com/youtubei/v1/player?key=AIzaSyAO_FJ2SlqU8Q4STEHLGCilw_Y9_11qcW8', $options);
        if (is_wp_error($request)) {
            return new WP_Error('youtube_api_error', $request->get_error_message());
        }

        if (200 !== $request['response']['code']) {
            if (429 == $request['response']['code']) {
                return new WP_Error('youtube_api_limit_reached', __('Youtube api limit reached'));
            } else {
                return new WP_Error('youtube_api_unknown_response', $request['response']['message']);
            }
        }

        parse_str($request['body'], $video_info_array);

        if (!property_exists(json_decode($video_info_array['player_response']), 'captions') && !isset(json_decode($video_info_array['player_response'])->captions)) {
            $langs['captions'][0] = '';
            $langs['translationLanguages'][0] = '';
            return $langs;
        }
        $languages_info = json_decode($video_info_array['player_response'])->captions->playerCaptionsTracklistRenderer;
        $langs = array();
        $i = 0;

        foreach ($languages_info->captionTracks as $lang) {
            $langs['captions'][$i]['name'] = $lang->name->simpleText;
            $langs['captions'][$i]['languageCode'] = $lang->languageCode;
            $langs['captions'][$i]['url'] = $lang->baseUrl;
            $i++;
        }

        $i = 0;
        foreach ($languages_info->translationLanguages as $trans) {
            $langs['translationLanguages'][$i]['name'] = $trans->languageName->simpleText;
            $langs['translationLanguages'][$i]['languageCode'] = $trans->languageCode;
            $langs['translationLanguages'][$i]['url'] = $langs['captions'][0]['url'] . '&tlang=' . $trans->languageCode;
            $i++;
        }

        return $langs;
    }

    public function convertToParagraphs($captions)
    {
        $captions_array = explode('. ', $captions);
        $captions_new_array = array();
        $temp = '';

        $random_word_count = rand(60, 200);
        $word_count = 0;
        $c = 0;
        foreach ($captions_array as $caption) {
            $word_count += str_word_count(strip_tags($caption));
            //echo "(" . $word_count . ") " . $caption . "<br>";

            if ($word_count >= $random_word_count) {
                $captions_new_array[] = ucfirst($temp . $caption);
                $temp = '';
                $word_count = 0;
                $random_word_count = rand(60, 200);
            } else {
                $temp .= $caption . '. ';
            }
            $c++;
        }

        if (!empty($temp)) {
            $captions_new_array[] = str_replace('. .', '.', $temp);
        }

        $contentio_video_captions = implode('.<br><br>', $captions_new_array);

        return $contentio_video_captions;
    }

}
