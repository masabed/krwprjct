/// <reference types="youtube" />
import { PropType } from 'vue';
export declare const PlayerState: {
    UNSTARTED: number;
    ENDED: number;
    PLAYING: number;
    PAUSED: number;
    BUFFERING: number;
    CUED: number;
};
export declare type SuggestedVideoQuality = YT.SuggestedVideoQuality;
export declare type PlayerVars = YT.PlayerVars;
declare const YouTube: import("vue").DefineComponent<{
    src: {
        type: PropType<string>;
        required: true;
    };
    height: {
        type: PropType<string | number>;
        default: number;
    };
    width: {
        type: PropType<string | number>;
        default: number;
    };
    host: {
        type: PropType<string>;
        default: string;
    };
    vars: PropType<YT.PlayerVars>;
}, unknown, {
    promise: Promise<void>;
    resolver: () => void;
    player: null | YT.Player;
    initiated: boolean;
    ready: boolean;
    iframeStyle: Record<string, string>;
}, {
    id(): string;
    wrapperStyle(): Record<string, string>;
}, {
    initPlayer(): void;
    /**
     * Queues a video by ID.
     *
     * @param videoId   Video ID.
     * @param startSeconds   Time from which the video should start playing.
     * @param suggestedQuality   Suggested video player quality.
     */
    cueVideoById(videoId: string, startSeconds?: number | undefined, suggestedQuality?: "default" | "small" | "medium" | "large" | "hd720" | "hd1080" | "highres" | undefined): void;
    /**
     * Loads and plays a video by ID.
     *
     * @param videoId   Video ID.
     * @param startSeconds   Time from which the video should start playing.
     * @param suggestedQuality   Suggested video player quality.
     */
    loadVideoById(videoId: string, startSeconds?: number | undefined, suggestedQuality?: "default" | "small" | "medium" | "large" | "hd720" | "hd1080" | "highres" | undefined): void;
    /**
     * Queues a video by media content URL.
     *
     * @param mediaContentUrl   Fully qualified player URL.
     * @param startSeconds   Time from which the video should start playing.
     * @param suggestedQuality   Suggested video player quality.
     */
    cueVideoByUrl(mediaContentUrl: string, startSeconds?: number | undefined, suggestedQuality?: "default" | "small" | "medium" | "large" | "hd720" | "hd1080" | "highres" | undefined): void;
    /**
     * Loads a video by media content URL.
     *
     * @param mediaContentUrl   Fully qualified player URL.
     * @param startSeconds   Time from which the video should start playing.
     * @param suggestedQuality   Suggested video player quality.
     */
    loadVideoByUrl(mediaContentUrl: string, startSeconds?: number | undefined, suggestedQuality?: "default" | "small" | "medium" | "large" | "hd720" | "hd1080" | "highres" | undefined): void;
    /**
     * Queues a playlist of videos.
     *
     * @param playlist   Video ID(s) to play.
     * @param index   Start index of the playlist, if not 0.
     * @param startSeconds   Time from which the video should start playing.
     * @param suggestedQuality   Suggested video player quality.
     */
    cuePlaylist(playlist: string | string[], index?: number | undefined, startSeconds?: number | undefined, suggestedQuality?: "default" | "small" | "medium" | "large" | "hd720" | "hd1080" | "highres" | undefined): void;
    /**
     * Loads a playlist of videos.
     *
     * @param playlist   Video ID(s) to play.
     * @param index   Start index of the playlist, if not 0.
     * @param startSeconds   Time from which the video should start playing.
     * @param suggestedQuality   Suggested video player quality.
     */
    loadPlaylist(playlist: string | string[], index?: number | undefined, startSeconds?: number | undefined, suggestedQuality?: "default" | "small" | "medium" | "large" | "hd720" | "hd1080" | "highres" | undefined): void;
    /**
     * Plays the currently cued/loaded video.
     */
    playVideo(): void;
    /**
     * Pauses the currently playing video.
     */
    pauseVideo(): void;
    /**
     * Stops and cancels loading of the current video.
     */
    stopVideo(): void;
    /**
     * Seeks to a specified time in the video.
     *
     * @param seconds   Time, in seconds from the beginning of the video.
     * @param allowSeekAhead   Whether the player is allowed to make a new request for unbuffered data.
     */
    seekTo(seconds: number, allowSeekAhead: boolean): void;
    /**
     * Loads and plays the next video in the playlist.
     */
    nextVideo(): void;
    /**
     * Loads and plays the previous video in the playlist.
     */
    previousVideo(): void;
    /**
     * Loads and plays the specified video in the playlist.
     *
     * @param index   Index of the video to play.
     */
    playVideoAt(index: number): void;
    /**
     * Mutes the player.
     */
    mute(): void;
    /**
     * Unmutes the player.
     */
    unMute(): void;
    /**
     * @returns Whether the player is muted.
     */
    isMuted(): boolean;
    /**
     * Sets the player volume.
     *
     * @param volume   An integer between 0 and 100.
     */
    setVolume(volume: number): void;
    /**
     * @returns The player's current volume, an integer between 0 and 100.
     */
    getVolume(): number;
    /**
     * @returns Playback rate of the currently playing video.
     */
    getPlaybackRate(): number;
    /**
     * Sets the suggested playback rate for the current video.
     *
     * @param suggestedRate   Suggested playback rate.
     */
    setPlaybackRate(suggestedRate: number): void;
    /**
     * @returns Available playback rates for the current video.
     */
    getAvailablePlaybackRates(): number[];
    /**
     * Sets whether the player should continuously play a playlist.
     *
     * @param loopPlaylists   Whether to continuously loop playlists.
     */
    setLoop(loopPlaylists: boolean): void;
    /**
     * Sets whether a playlist's videos should be shuffled.
     *
     * @param shufflePlaylist   Whether to shuffle playlist videos.
     */
    setShuffle(shufflePlaylist: boolean): void;
    /**
     * @returns A number between 0 and 1 of how much the player has buffered.
     */
    getVideoLoadedFraction(): number;
    /**
     * @returns Current player state.
     */
    getPlayerState(): YT.PlayerState;
    /**
     * @returns Elapsed time in seconds since the video started playing.
     */
    getCurrentTime(): number;
    /**
     * @returns Actual video quality of the current video.
     */
    getPlaybackQuality(): SuggestedVideoQuality;
    /**
     * Sets the suggested video quality for the current video.
     *
     * @param suggestedQuality   Suggested video quality for the current video.
     */
    setPlaybackQuality(suggestedQuality: SuggestedVideoQuality): void;
    /**
     * @returns Quality formats in which the current video is available.
     */
    getAvailableQualityLevels(): SuggestedVideoQuality[];
    /**
     * @returns Duration in seconds of the currently playing video.
     */
    getDuration(): number;
    /**
     * @returns YouTube.com URL for the currently loaded/playing video.
     */
    getVideoUrl(): string;
    /**
     * @returns Embed code for the currently loaded/playing video.
     */
    getVideoEmbedCode(): string;
    /**
     * @returns Video IDs in the playlist as they are currently ordered.
     */
    getPlaylist(): string[];
    /**
     * @returns Index of the playlist video that is currently playing.
     */
    getPlaylistIndex(): number;
}, import("vue").ComponentOptionsMixin, import("vue").ComponentOptionsMixin, Record<string, any>, string, import("vue").VNodeProps & import("vue").AllowedComponentProps & import("vue").ComponentCustomProps, Readonly<{
    src: string;
    height: string | number;
    width: string | number;
    host: string;
} & {
    vars?: YT.PlayerVars | undefined;
}>, {
    height: string | number;
    width: string | number;
    host: string;
}>;
export declare type Methods = (typeof YouTube)['methods'];
export default YouTube;
