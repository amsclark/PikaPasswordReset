# PikaPasswordReset

You must create the table with:
```
CREATE TABLE `pw_reset_tokens` (
  `token_id` int(11) NOT NULL DEFAULT '0',
  `username` varchar(25) NOT NULL DEFAULT '',
  `token` varchar(128) NOT NULL DEFAULT '',
  `used` tinyint(4) NOT NULL DEFAULT '0',
  `token_expire` int(11) DEFAULT '0'
)
```
