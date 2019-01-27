<?php

namespace DiscordDaemon;

use Discord\Discord;
use Discord\WebSockets\Event;
use Discord\Voice\VoiceClient;
use DiscordDaemon\StreamQueue\GuildList;
use DiscordDaemon\StreamQueue\MasterQueue;

/**
 * @author Ammar Faizi <ammarfaizi2@gmail.com> https://www.facebook.com/ammarfaizi2
 * @license MIT
 * @package \DiscordDaemon
 * @version 0.0.1
 */
class StreamQueue
{
	/**
	 * @var \DiscordDaemon\Bot
	 */
	private $bot;

	/**
	 * @param \DiscordDaemon\Bot $bot
	 *
	 * Constructor.
	 */
	public function __construct(Bot $bot)
	{
		$this->bot = $bot;
	}

	/**
	 * @return void
	 */
	public function run(): void
	{
		while (true) {
			$guildList = new GuildList;
			foreach ($guildList->get() as $k => &$v) {
				$this->dispatch($v);
			}
			unset($guildList);
			printf("[StreamQueue] Sleeping for 10 seconds");
			for ($i=0; $i < 10; $i++) {
				sleep(1);
				printf(".");
			}
			printf("\n");
		}
	}

	/**
	 * @param string &$guild_id
	 * @return void
	 */
	public function dispatch(string &$guild_id): void
	{
		printf("Dispatching %s stream queue...\n", $guild_id);
		$st = new MasterQueue($guild_id);
		if ($st->countQueue()) {
			$st = $st->dequeue();
			printf("Preparing download for %s...\n", $st);
			$this->bot->init();
			$this->bot->discord->on("ready", function ($discord) use (&$st) {

				$r = sprintf("Downloading \"%s\"...", $st);

				$guild = $discord->guilds->first();
				$channel = $guild->channels->getAll("type", "text")->first();
				
				$act = function () {
					$ytkernel = new YoutubeKernel($st, STORAGE_PATH."/mp3");
					$ytkernel->run();
					$ytkernel->join();
					exit;
				};

				$channel->sendMessage($r)->then(function ($message) use ($act) {
					$act();
				    printf("The message was sent!\n");
				})->otherwise(function ($e) use ($act) {
					$act();
				    printf("There was an error sending the message: %s\n", $e->getMessage());
				});

			});
			$this->bot->discord->run();
		} else {
			printf("There is no queue for guild %s\n", $guild_id);
		}
	}
}
