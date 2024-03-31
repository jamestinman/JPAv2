var allPlaylists={
	0:{
		'name':'blank box',
		'author':'Bruce Grove',
		'hasVideo':false,
		'tracks':[]
	},
	1:{
		'name':'Joe Boyd Record Box',
		'author':'Bruce Grove',
		'hasVideo':true,
		'dynamic':false,
		'tracks':[
			// pink floyd
			{'title':'Bike','artist':'Pink Floyd','type':'SoundManager','source':'pink-floyd-bike','outputTo':'pinkFloydAudio','icon':'pink-floyd-piper-at-the-gates-of-dawn-sleeve.jpg'},

			// ammmusic
			{'title':'After Rapidly Circling The Plaza','artist':'AMMmusic','type':'SoundManager','source':'ammmusic-after-rapidly-circling-the-plaza','outputTo':'ammAudio','offScreen':true,'icon':'ammmusic-sleeve.jpg'},

			// the purple gang 
			{'title':'Granny Takes A Trip','artist':'The Purple Gang','type':'SoundManager','source':'the-purple-gang-granny-takes-a-trip','outputTo':'purpleAudio','offScreen':true,'icon':'the-purple-gang-strikes-sleeve.jpg'},

			// charles mingus - 
			{'title':'Fables of Faubus','artist':'Charles Mingus','type':'SoundManager','source':'charlie-mingus-fables-of-faubus','outputTo':'mingusAudio','icon':'charles-mingus-ah-um-sleeve.jpg'},
			
			// pipes of pan
			{'title':'Side A','artist':'Pipes of Pan','type':'SoundManager','source':'pipes-of-pan-side-a','outputTo':'pipesAudio','icon':'brian-jones-pipes-of-pan-sleeve.jpg'},
			{'title':'Pipes of Pan','artist':'Joe Boyd','type':'YouTube','source':'ngVsi9J2r_A','outputTo':'pipesVideo','splashImage':'joe-boyd-joujouka.jpg',
				'fallback':{'title':'Pipes of Pan','artist':'Joe Boyd','type':'SoundManager','source':'joe-boyd-pipes-of-pan','outputTo':'pipesVideo','splashImage':'brian-jones-pipes-of-pan-sleeve.jpg'}
			},
			
			// 10,000 maniacs 
			{'title':'Scorpio Rising','artist':'10,000 Maniacs','type':'SoundManager','source':'10000-maniacs-scorpio-rising','outputTo':'maniacsAudio','icon':'10000-maniacs-the-wishing-chair-sleeve.jpg'},

			// bob dylan
			{'title':'Million Dollar Bash','artist':'Bob Dylan','type':'SoundManager','source':'bob-dylan-million-dollar-bash','outputTo':'dylanAudio','icon':'bob-dylan-sleeve.jpg'},
			{'title':'Bob Dylan','artist':'Joe Boyd','type':'YouTube','source':'IN68RyVJSgw','outputTo':'dylanVideo','splashImage':'joe-boyd-bob-dylan.jpg',
				'fallback':{'title':'Bob Dylan','artist':'Joe Boyd','type':'SoundManager','source':'joe-boyd-dylan','outputTo':'dylanVideo','icon':'bob-dylan-sleeve.jpg'}
			},

			// fairport -  
			{'title':'A Sailor\'s Life','artist':'Fairport Convention','type':'SoundManager','source':'fairport-convention-a-sailors-life','outputTo':'fairportAudio','icon':'fairport-convention-unhalfbricking-sleeve.jpg'},
			{'title':'Unhalfbricking','artist':'Joe Boyd','type':'YouTube','source':'243jyoOoEVU','outputTo':'fairportVideo','splashImage':'joe-boyd-fairport.jpg',
				'fallback':{'title':'Unhalfbricking','artist':'Joe Boyd','type':'SoundManager','source':'joe-boyd-fairport','outputTo':'fairportVideo','splashImage':'joe-boyd-fairport.jpg'}
			},

			// nick drake
			{'title':'Day is Done','artist':'Nick Drake','type':'SoundManager','source':'nick-drake-day-is-done','outputTo':'nickDrakeAudio','icon':'nick-drake-5-leaves-left-sleeve.jpg'},
			{'title':'Five Leaves Left','artist':'Joe Boyd','type':'YouTube','source':'rOb2X8XVRuQ','outputTo':'nickDrakeVideo','splashImage':'joe-boyd-nick-drake.jpg',
				'fallback':{'title':'Five Leaves Left','artist':'Joe Boyd','type':'SoundManager','source':'joe-boyd-nick-drake','outputTo':'nickDrakeVideo'}
			},

			// geoff mulduar
			{'title':'Higher & Higher','artist':'Geoff Muldaur','type':'SoundManager','source':'geoff-mulduar-higher-and-higher','outputTo':'mulduarAudio','icon':'geoff-muldaur-sleeve.jpg'},
			
			// them / van morrison
			{'title':'Gloria','artist':'Them','type':'SoundManager','source':'them-gloria','outputTo':'themAudio','icon':'them-van-morrison-sleeve.jpg'},

			// boz scaggs
			{'title':'Dinah Flo','artist':'Boz Scaggs','type':'SoundManager','source':'boz-scaggs-dinah-flo','outputTo':'bozAudio','icon':'boz-scaggs-my-time-sleeve.jpg'},
			
			// thomas mapfumo 
			{'title':'Shumba','artist':'Thomas Mapfumo','type':'SoundManager','source':'thomas-mapfumo-shumba','outputTo':'mapfumoAudio','icon':'thomas-mapfumo-sleeve.jpg'},
			
			// moby grape 
			{'title':'Omaha','artist':'Moby Grape','type':'SoundManager','source':'moby-grape-omaha','outputTo':'mobyAudio','icon':'moby-grape-sleeve.jpg'},
			
			// lightnin hopkins 
			{'title':'Blues for Queen Elizabeth','artist':'Lightnin\' Hopkins','type':'SoundManager','source':'lightnin-hopkins-blues-for-queen-elizabeth','outputTo':'lightninAudio','icon':'sam-lightnin-hopkins-sleeve.jpg'},
			
			// rural blues
			{'title':'Shelby County Work House Blues','artist':'Hambone Willie Newburn','type':'SoundManager','source':'hambone-willie-newburn-shelby-county-work-house-blues','outputTo':'ruralAudio','icon':'rural-blues-sleeve.jpg'},
			{'title':'The Rural Blues','artist':'Joe Boyd','type':'YouTube','source':'h90s3OIgo0w','outputTo':'ruralVideo','splashImage':'joe-boyd-rural-blues.jpg',
				'fallback':{'title':'The Rural Blues','artist':'Joe Boyd','type':'SoundManager','source':'joe-boyd-rural-blues','outputTo':'ruralVideo','splashImage':'record-box-logo-inverse.png'}
			},

			// chess golden decade 
			{'title':'Sincerely','artist':'The Moonglows','type':'SoundManager','source':'the-moonglows-sincerely','outputTo':'chessAudio','icon':'chess-golden-decade-sleeve.jpg'},
			
			// denny laine 
			{'title':'Say You Don\'t Mind','artist':'Denny Laine','type':'SoundManager','source':'denny-laine-say-you-dont-mind','outputTo':'dennyAudio','offScreen':true,'icon':'denny-laine-say-you-dont-mind-front-480x460.jpg'},
			
			// tomorrow
			{'title':'My White Bicycle','artist':'Tomorrow','type':'SoundManager','source':'tomorrow-my-white-bicycle','outputTo':'tomorrowAudio','offScreen':true,'icon':'tomorrow-front-480x460.jpg'},
			
			// toots and the maytals 
			{'title':'Struggle','artist':'Toots and the Maytals','type':'SoundManager','source':'toots-and-the-maytals-struggle','outputTo':'tootsAudio','offScreen':true,'icon':'toots-front-480x460.jpg'},
			{'title':'Toots and the Maytals','artist':'Joe Boyd','type':'YouTube','source':'7EMDeiJWZgQ','outputTo':'tootsVideo','splashImage':'joe-boyd-toots.jpg',
				'fallback':{'title':'Toots and the Maytals','artist':'Joe Boyd','type':'SoundManager','source':'joe-boyd-toots','outputTo':'tootsVideo','icon':'toots-front-480x460.jpg'}
			},

			// pink floyd - arnold layne
			{'title':'Arnold Layne','artist':'Pink Floyd','type':'SoundManager','source':'pink-floyd-arnold-layne','outputTo':'arnoldLayneAudio','icon':'pink-floyd-arnold-lane-front-480x460.jpg'},
			
			// bonzo dog band
			{'title':'The Intro and the Outro','artist':'Bonzo Dog Band','type':'SoundManager','source':'bonzo-dog-band-the-intro-and-the-outro','outputTo':'bonzoDogAudio','icon':'bonzo-dog-band-front-480x460.jpg'},
			
			// talking heads
			{'title':'Take Me To The River','artist':'Talking Heads','type':'SoundManager','source':'talking-heads-take-me-to-the-river','outputTo':'talkingHeadsAudio','icon':'talking-heads-sleeve.jpg'}
		]	
	},
	2:{
		'name':'John Peel\' Desert Island Discs',
		'author':'Bruce Grove',
		'hasVideo':false,
		'dynamic':false,
		'tracks':[
			{'title':'Zadok the Priest','artist':'Handel','type':'SoundManager','source':'handel-zadok-the-priest','outputTo':'handelAudio','icon':'handel-front.jpg'},
			{'title':'It\'s Over','artist':'Roy Orbison','type':'SoundManager','source':'roy-orbison-its-over','outputTo':'royOrbisonAudio','icon':'roy-orbison-its-over.jpg'},
			{'title':'Too Much','artist':'Jimmy Reed','type':'SoundManager','source':'jimmy-reed-too-much','outputTo':'jimmyReedAudio','icon':'jimmy-reed-front.jpg'},
			{'title':'Man Kind','artist':'Misty in Roots','type':'SoundManager','source':'misty-in-roots-man-kind','outputTo':'mistyLiveAudio','icon':'misty-in-roots-live-front.jpg'},
			{'title':'Teenage Kicks','artist':'The Undertones','type':'SoundManager','source':'the-undertones-teenage-kicks','outputTo':'teenageKicksAudio','icon':'teenage-kicks-front.jpg'},
			{'title':'Eat Yourself Fitter','artist':'The Fall','type':'SoundManager','source':'the-fall-eat-yourself-fitter','outputTo':'fallEatAudio','icon':'the-fall-perverted-front.jpg'},
			{'title':'Pasi Pano Pane Zviedzo','artist':'The Four Brothers','type':'SoundManager','source':'the-four-brothers-pasi-pano-pane-zviedzo','outputTo':'fourBrothersAudio','icon':'the-four-brothers-front.jpg'}
		]	
	},
	3:{
		'name':'Mala Record Box',
		'author':'Bruce Grove',
		'hasVideo':true,
		'dynamic':false,
		'tracks':[
			{'title':'East of the River Nile','artist':'Augustus Pablo','type':'SoundManager','source':'augustus-pablo-east-of-the-river-nile','outputTo':'pabloERNAudio','icon':'augustus-pablo-east-sleeve.jpg'},
			{'title':'Man Kind','artist':'Misty in Roots','type':'SoundManager','source':'misty-in-roots-man-kind','outputTo':'mistyLiveAudio','icon':'misty-in-roots-live-front.jpg'},
			{'title':'King Tubby\'s Special Mix','artist':'Augustus Pablo','type':'SoundManager','source':'augustus-pablo-king-tubbys-special-mix','outputTo':'pabloTubbyAudio','icon':'augustus-pablo-king-tubby-front.jpg'},
			{'title':'Spliffhead (Remix)','artist':'Ragga Twins','type':'SoundManager','source':'the-ragga-twins-reggae-owes-me-money-spliffhead-remix','outputTo':'raggaTwinsAudio','icon':'ragga-twins-reggae-owes-me-money-front.jpg'},
			{'title':'You Got Me Burnin\'','artist':'Cloud 9','type':'SoundManager','source':'cloud-9-you-got-me-burnin','outputTo':'cloud9Audio','icon':'cloud-9-you-got-me-burnin-front.jpg'},
			{'title':'Lord of the Null Lines','artist':'Hyper-on Experience','type':'SoundManager','source':'hyper-on-experience-lords-of-the-null-lines','outputTo':'hyperAudio','icon':'hyper-on-experience-lord-of-the-null-lines-front.jpg'},
			{'title':'Maxi','artist':'Tom and Jerry','type':'SoundManager','source':'tom-and-jerry-maxi','outputTo':'tomJerryMaxiAudio','icon':'tom-and-jerry-maxi-front.jpg'},
			{'title':'Manhattan Melody','artist':'Lemon D','type':'SoundManager','source':'lemon-d-manhattan-melody','outputTo':'lemonDManhattanAudio','icon':'lemon-d-jah-love-front.jpg'},
			
			{'title':'Antique Toy','artist':'The Future Sound of London','type':'SoundManager','source':'the-future-sound-of-london-antique-toy','outputTo':'fsolAudio','icon':'future-sound-of-london-dead-cities-front.jpg'},
			{'title':'The Future Sound of London','artist':'Mala','type':'YouTube','source':'1KNqjTLDJ_4','outputTo':'fsolVideo','splashImage':'future-sound-of-london-dead-cities-front.jpg',
				'fallback':{'title':'The Future Sound of London','artist':'Mala','type':'SoundManager','source':'mala-future-sound','outputTo':'fsolVideo','splashImage':'future-sound-of-london-dead-cities-front.jpg'}
			},
			{'title':'Digital','artist':'Goldie','type':'SoundManager','source':'goldie-digital','outputTo':'goldieDigitalAudio','icon':'goldie-digital-front.jpg'},
			{'title':'Fade to Black','artist':'Souljah','type':'SoundManager','source':'souljah-fade-to-black','outputTo':'souljahFadeAudio','icon':'souljah-fade-to-black-front.jpg'},
			{'title':'Juggle Tings Proper','artist':'Roots Manuva','type':'SoundManager','source':'roots-manuva-juggle-tings-proper','outputTo':'rootsManuvaJuggleAudio','icon':'roots-manuva-juggle-tings-proper-front.jpg'},
			{'title':'Red','artist':'Artwork','type':'SoundManager','source':'artwork-red','outputTo':'artworkRedAudio','icon':'artwork-red-ep-front.jpg'},
			{'title':'Artwork / Menta','artist':'Mala','type':'YouTube','source':'ODe5YWm-xRo','outputTo':'artworkRedVideo','splashImage':'artwork-red-ep-front.jpg',
				'fallback':{'title':'Artwork / Menta','artist':'Mala','type':'SoundManager','source':'mala-artwork','outputTo':'artworkRedVideo','splashImage':'artwork-red-ep-front.jpg'}
			},

			{'title':'Thriller Funk','artist':'Slaughter Mob','type':'SoundManager','source':'the-slaughter-mob-thriller-funk','outputTo':'slaughterThrillerAudio','icon':'slaughter-mob-saddam-front.jpg'},
			{'title':'Slaughter Mob','artist':'Mala','type':'YouTube','source':'_nNyAClrJOY','outputTo':'slaughterThrillerVideo','splashImage':'slaughter-mob-saddam-front.jpg',
				'fallback':{'title':'Slaughter Mob','artist':'Mala','type':'SoundManager','source':'mala-slaughter-mob','outputTo':'slaughterThrillerVideo','splashImage':'slaughter-mob-saddam-front.jpg'}
			},

			{'title':'Wot Do U Call It?','artist':'Wiley','type':'SoundManager','source':'wiley-wot-do-u-call-it','outputTo':'wileyWotAudio','icon':'wiley-wot-u-call-it-front.jpg'},
			{'title':'Wiley','artist':'Mala','type':'YouTube','source':'A0CH3ovfjCM','outputTo':'wileyWotVideo','splashImage':'wiley-wot-u-call-it-front.jpg',
				'fallback':{'title':'Sign of the Dub','artist':'Mala','type':'SoundManager','source':'mala-wiley','outputTo':'wileyWotVideo','splashImage':'wiley-wot-u-call-it-front.jpg'}
			},
			{'title':'Walking Bass','artist':'Benga','type':'SoundManager','source':'benga-walking-bass','outputTo':'bengaWalkingAudio','icon':'benga-skream-hydro-front.jpg'},{'title':'Walking Bass','artist':'Benga','type':'SoundManager','source':'benga-walking-bass','outputTo':'bengaWalkingAudio','icon':'benga-skream-hydro-front.jpg'},
			{'title':'Ugly','artist':'Digital Mystikz','type':'SoundManager','source':'digital-mystikz-ugly','outputTo':'digitalMystikzUglyAudio','icon':'digital-mystikz-pathways-front.jpg'},
			{'title':'Twisup','artist':'Loefah','type':'SoundManager','source':'digital-mystikz-twisup','outputTo':'digitalMystikzTwisupAudio','icon':'digital-mystikz-twisup-front.jpg'},
			
			{'title':'Sign of the Dub','artist':'Kode 9 & Daddi G','type':'SoundManager','source':'kode-9-and-daddi-g-sign-of-the-dub','outputTo':'kode9SignAudio','icon':'kode-9-daddi-gee-sign-of-the-dub-front.jpg'},
			{'title':'Sign of the Dub','artist':'Mala','type':'YouTube','source':'inmRPzKYwRI','outputTo':'kode9SignVideo','splashImage':'kode-9-daddi-gee-sign-of-the-dub-front.jpg',
				'fallback':{'title':'Sign of the Dub','artist':'Mala','type':'SoundManager','source':'mala-sign-of-the-dub','outputTo':'kode9SignVideo','splashImage':'kode-9-daddi-gee-sign-of-the-dub-front.jpg'}
			}
			//{'title':'Java','artist':'Augustus Pablo','type':'SoundManager','source':'augustus-pablo-java','outputTo':'pabloJavaAudio','icon':'augustus-pablo-java-480x460.jpg'},
			//{'title':'Rocker\'s Special','artist':'Pablo All Stars','type':'SoundManager','source':'pablo-all-stars-rockers-special','outputTo':'pabloRockersAudio','icon':'augustus-pablo-rockers-front.jpg'},
			//{'title':'Oh Wicked Man','artist':'Misty in Roots','type':'SoundManager','source':'misty-in-roots-oh-wicked-man','outputTo':'mistyOWMAudio','icon':'misty-oh-wicked-man-back.jpg'},
			//{'title':'Let off Sup\'m','artist':'Gregory Isaacs & Dennis Brown','type':'SoundManager','source':'gregory-isaacs-and-dennis-brown-let-off-supm','outputTo':'gregIsaacsAudio','icon':'gregory-isaacs-and-dennis-brown-front.jpg'},
			//{'title':'It\'s All Over','artist':'Tom and Jerry','type':'SoundManager','source':'tom-and-jerry-its-all-over','outputTo':'tomJerryAllOverAudio','icon':'tom-and-jerry-its-all-over-front.jpg'},
			//{'title':'Feel It','artist':'Lemon D','type':'SoundManager','source':'lemon-d-feel-it','outputTo':'lemonDFeelItAudio','icon':'lemon-d-feel-it-front.jpg'},
			//{'title':'Makes me wanna die','artist':'Tricky','type':'SoundManager','source':'tricky-makes-me-wanna-die','outputTo':'trickyAudio','icon':'tricky-tricky-kid-front.jpg'},
			//{'title':'It\'s Jazzy','artist':'Roni Size','type':'SoundManager','source':'roni-size-its-jazzy-felix-road-mix','outputTo':'vClassicAudio','icon':'v-classic-front.jpg'},
			//{'title':'Sing (Time)','artist':'Terrorist','type':'SoundManager','source':'terrorist-sing-time','outputTo':'rayKeithVintageAudio','icon':'ray-keith-vintage-dread-2000-front.jpg'},	
			//{'title':'Snake Charmer','artist':'Menta','type':'SoundManager','source':'menta-snake-charmer','outputTo':'mentaSnakeAudio','icon':'menta-snake-charmer-front.jpg'},	
			//{'title':'Gram','artist':'Jon E Cash','type':'SoundManager','source':'jon-e-cash-gram','outputTo':'djDreaddGramAudio','icon':'dj-dreadd-gram-front.jpg'},
		]	
	},
	4:{
		'name':'John Peel Dynamic',
		'author':'Bruce Grove',
		'hasVideo':false,
		'dynamic':true,
		'folder':'dynamic-content-test',
		'tracks':[
			{'title':'Zadok the Priest','artist':'Handel','type':'SoundManager','source':'handel-zadok-the-priest','outputTo':'handelAudio','icon':'handel-front.jpg'},
			{'title':'It\'s Over','artist':'Roy Orbison','type':'SoundManager','source':'roy-orbison-its-over','outputTo':'royOrbisonAudio','icon':'roy-orbison-its-over.jpg'},
			{'title':'Too Much','artist':'Jimmy Reed','type':'SoundManager','source':'jimmy-reed-too-much','outputTo':'jimmyReedAudio','icon':'jimmy-reed-front.jpg'},
			{'title':'Man Kind','artist':'Misty in Roots','type':'SoundManager','source':'misty-in-roots-man-kind','outputTo':'mistyLiveAudio','icon':'misty-in-roots-live-front.jpg'},
			{'title':'Teenage Kicks','artist':'The Undertones','type':'SoundManager','source':'the-undertones-teenage-kicks','outputTo':'teenageKicksAudio','icon':'teenage-kicks-front.jpg'},
			{'title':'Eat Yourself Fitter','artist':'The Fall','type':'SoundManager','source':'the-fall-eat-yourself-fitter','outputTo':'fallEatAudio','icon':'the-fall-perverted-front.jpg'},
			{'title':'Pasi Pano Pane Zviedzo','artist':'The Four Brothers','type':'SoundManager','source':'the-four-brothers-pasi-pano-pane-zviedzo','outputTo':'fourBrothersAudio','icon':'the-four-brothers-front.jpg'}
		]	
	},

	5:{
		'name':'Joe Boyd Book Chapter',
		'author':'Bruce Grove',
		'hasVideo':false,
		'dynamic':false,
		'tracks':[
			
			// BB King
			
			{'title':'Worry, Worry','artist':'B.B. King','type':'YouTube','source':'FI21T5FUgrs','outputTo':'bbkingVideo','splashImage':'bb-king-video.jpg','icon':'bb-king-sleeve.jpg',
				'fallback':{'title':'Worry, Worry','artist':'BB King','type':'SoundManager','source':'bbking','outputTo':'bbkingVideo','splashImage':'bb-king-sleeve.jpg'}
			},


			// James Brown
			
			{'title':'Lost Someone','artist':'James Brown','type':'YouTube','source':'59ogWXCONW8','outputTo':'jbVideo','splashImage':'james-brown.jpg','icon':'james-brown-sleeve.jpg',
				'fallback':{'title':'Lost Someone','artist':'James Brown','type':'SoundManager','source':'jamesbrown','outputTo':'jbVideo','splashImage':'james-brown-sleeve.jpg'}
			},

			// Beatles
			
			{'title':'I Saw Her Standing There','artist':'the Beatles','type':'YouTube','source':'uZMQU4c1pEg','outputTo':'beatlesVideo','splashImage':'beatles.jpg','icon':'beatles-sleeve.jpg',
				'fallback':{'title':'I Saw Her Standing There','artist':'the Beatles','type':'SoundManager','source':'beatles','outputTo':'beatlesVideo','splashImage':'beatles-sleeve.jpg'}
			},

			// Tokens
			
			{'title':'The Lion Sleeps Tonight','artist':'the Tokens','type':'YouTube','source':'GxwoxWOd_dc','outputTo':'tokensVideo','splashImage':'tokens-sheild.jpg','icon':'tokens-sleeve.jpg',
				'fallback':{'title':'The Lion Sleeps Tonight','artist':'the Tokens','type':'SoundManager','source':'tokens','outputTo':'tokensVideo','splashImage':'tokens-sleeve.jpg'}
			},

			// Weavers
			
			{'title':'Wimoweh','artist':'the Weavers','type':'YouTube','source':'77VUYPVMtWY','outputTo':'weaversVideo','splashImage':'weavers.jpg','icon':'weavers-sleeve.jpg',
				'fallback':{'title':'Wimoweh','artist':'the Weavers','type':'SoundManager','source':'weavers','outputTo':'weaversVideo','splashImage':'tokens-sleeve.jpg'}
			},

			// Tight Fit
			
			{'title':'The Lion Sleeps Tonight','artist':'Tight Fit','type':'YouTube','source':'0cD9cBEaNBc','outputTo':'tightfitVideo','splashImage':'tight-fit.jpg','icon':'tight-fit-sleeve.jpg',
				'fallback':{'title':'The Lion Sleeps Tonight','artist':'Tight Fit','type':'SoundManager','source':'tightfit','outputTo':'tightfitVideo','splashImage':'tight-fit-sleeve.jpg'}
			},

			// Lion King
			
			{'title':'The Lion Sleeps Tonight','artist':'The Lion King','type':'YouTube','source':'4SBjSQweDsQ','outputTo':'lionkingVideo','splashImage':'lion-king-shield.jpg','icon':'lion-king-sleeve.jpg',
				'fallback':{'title':'The Lion Sleeps Tonight','artist':'Lion King','type':'SoundManager','source':'lionking','outputTo':'lionkingVideo','splashImage':'lion-king-sleeve.jpg'}
			},
			
			
		]	
	},
	6:{
		'name':'Don Letts',
		'author':'Bruce Grove',
		'hasVideo':false,
		'dynamic':false,
		'tracks':[
			// pink floyd
			{'title':'Bike','artist':'Pink Floyd','type':'SoundManager','source':'pink-floyd-bike','outputTo':'pinkFloydAudio','icon':'pink-floyd-piper-at-the-gates-of-dawn-sleeve.jpg'},

			// ammmusic
			{'title':'After Rapidly Circling The Plaza','artist':'AMMmusic','type':'SoundManager','source':'ammmusic-after-rapidly-circling-the-plaza','outputTo':'ammAudio','offScreen':true,'icon':'ammmusic-sleeve.jpg'},

			// the purple gang 
			{'title':'Granny Takes A Trip','artist':'The Purple Gang','type':'SoundManager','source':'the-purple-gang-granny-takes-a-trip','outputTo':'purpleAudio','offScreen':true,'icon':'the-purple-gang-strikes-sleeve.jpg'},

			// charles mingus - 
			{'title':'Fables of Faubus','artist':'Charles Mingus','type':'SoundManager','source':'charlie-mingus-fables-of-faubus','outputTo':'mingusAudio','icon':'charles-mingus-ah-um-sleeve.jpg'},
			
			// pipes of pan
			{'title':'Side A','artist':'Pipes of Pan','type':'SoundManager','source':'pipes-of-pan-side-a','outputTo':'pipesAudio','icon':'brian-jones-pipes-of-pan-sleeve.jpg'},
			{'title':'Pipes of Pan','artist':'Joe Boyd','type':'YouTube','source':'ngVsi9J2r_A','outputTo':'pipesVideo','splashImage':'joe-boyd-joujouka.jpg',
				'fallback':{'title':'Pipes of Pan','artist':'Joe Boyd','type':'SoundManager','source':'joe-boyd-pipes-of-pan','outputTo':'pipesVideo','splashImage':'brian-jones-pipes-of-pan-sleeve.jpg'}
			},
			
			// 10,000 maniacs 
			{'title':'Scorpio Rising','artist':'10,000 Maniacs','type':'SoundManager','source':'10000-maniacs-scorpio-rising','outputTo':'maniacsAudio','icon':'10000-maniacs-the-wishing-chair-sleeve.jpg'},

			// bob dylan
			{'title':'Million Dollar Bash','artist':'Bob Dylan','type':'SoundManager','source':'bob-dylan-million-dollar-bash','outputTo':'dylanAudio','icon':'bob-dylan-sleeve.jpg'},
			{'title':'Bob Dylan','artist':'Joe Boyd','type':'YouTube','source':'IN68RyVJSgw','outputTo':'dylanVideo','splashImage':'joe-boyd-bob-dylan.jpg',
				'fallback':{'title':'Bob Dylan','artist':'Joe Boyd','type':'SoundManager','source':'joe-boyd-dylan','outputTo':'dylanVideo','icon':'bob-dylan-sleeve.jpg'}
			},

			// fairport -  
			{'title':'A Sailor\'s Life','artist':'Fairport Convention','type':'SoundManager','source':'fairport-convention-a-sailors-life','outputTo':'fairportAudio','icon':'fairport-convention-unhalfbricking-sleeve.jpg'},
			{'title':'Unhalfbricking','artist':'Joe Boyd','type':'YouTube','source':'243jyoOoEVU','outputTo':'fairportVideo','splashImage':'joe-boyd-fairport.jpg',
				'fallback':{'title':'Unhalfbricking','artist':'Joe Boyd','type':'SoundManager','source':'joe-boyd-fairport','outputTo':'fairportVideo','splashImage':'record-box-logo-inverse.png'}
			},

			// nick drake
			{'title':'Day is Done','artist':'Nick Drake','type':'SoundManager','source':'nick-drake-day-is-done','outputTo':'nickDrakeAudio','icon':'nick-drake-5-leaves-left-sleeve.jpg'},
			{'title':'Five Leaves Left','artist':'Joe Boyd','type':'YouTube','source':'rOb2X8XVRuQ','outputTo':'nickDrakeVideo','splashImage':'joe-boyd-nick-drake.jpg',
				'fallback':{'title':'Five Leaves Left','artist':'Joe Boyd','type':'SoundManager','source':'joe-boyd-nick-drake','outputTo':'nickDrakeVideo'}
			},

			// geoff mulduar
			{'title':'Higher & Higher','artist':'Geoff Muldaur','type':'SoundManager','source':'geoff-mulduar-higher-and-higher','outputTo':'mulduarAudio','icon':'geoff-muldaur-sleeve.jpg'},
			
			// them / van morrison
			{'title':'Gloria','artist':'Them','type':'SoundManager','source':'them-gloria','outputTo':'themAudio','icon':'them-van-morrison-sleeve.jpg'},

			// boz scaggs
			{'title':'Dinah Flo','artist':'Boz Scaggs','type':'SoundManager','source':'boz-scaggs-dinah-flo','outputTo':'bozAudio','icon':'boz-scaggs-my-time-sleeve.jpg'},
			
			// thomas mapfumo 
			{'title':'Shumba','artist':'Thomas Mapfumo','type':'SoundManager','source':'thomas-mapfumo-shumba','outputTo':'mapfumoAudio','icon':'thomas-mapfumo-sleeve.jpg'},
			
			// moby grape 
			{'title':'Omaha','artist':'Moby Grape','type':'SoundManager','source':'moby-grape-omaha','outputTo':'mobyAudio','icon':'moby-grape-sleeve.jpg'},
			
			// lightnin hopkins 
			{'title':'Blues for Queen Elizabeth','artist':'Lightnin\' Hopkins','type':'SoundManager','source':'lightnin-hopkins-blues-for-queen-elizabeth','outputTo':'lightninAudio','icon':'sam-lightnin-hopkins-sleeve.jpg'},
			
			// rural blues
			{'title':'Shelby County Work House Blues','artist':'Hambone Willie Newburn','type':'SoundManager','source':'hambone-willie-newburn-shelby-county-work-house-blues','outputTo':'ruralAudio','icon':'rural-blues-sleeve.jpg'},
			{'title':'The Rural Blues','artist':'Joe Boyd','type':'YouTube','source':'h90s3OIgo0w','outputTo':'ruralVideo','splashImage':'joe-boyd-rural-blues.jpg',
				'fallback':{'title':'The Rural Blues','artist':'Joe Boyd','type':'SoundManager','source':'joe-boyd-rural-blues','outputTo':'ruralVideo','splashImage':'record-box-logo-inverse.png'}
			},

			// chess golden decade 
			{'title':'Sincerely','artist':'The Moonglows','type':'SoundManager','source':'the-moonglows-sincerely','outputTo':'chessAudio','icon':'chess-golden-decade-sleeve.jpg'},
			
			// denny laine 
			{'title':'Say You Don\'t Mind','artist':'Denny Laine','type':'SoundManager','source':'denny-laine-say-you-dont-mind','outputTo':'dennyAudio','offScreen':true,'icon':'denny-laine-say-you-dont-mind-front-480x460.jpg'},
			
			// tomorrow
			{'title':'My White Bicycle','artist':'Tomorrow','type':'SoundManager','source':'tomorrow-my-white-bicycle','outputTo':'tomorrowAudio','offScreen':true,'icon':'tomorrow-front-480x460.jpg'},
			
			// toots and the maytals 
			{'title':'Struggle','artist':'Toots and the Maytals','type':'SoundManager','source':'toots-and-the-maytals-struggle','outputTo':'tootsAudio','offScreen':true,'icon':'toots-front-480x460.jpg'},
			{'title':'Toots and the Maytals','artist':'Joe Boyd','type':'YouTube','source':'7EMDeiJWZgQ','outputTo':'tootsVideo','splashImage':'joe-boyd-toots.jpg',
				'fallback':{'title':'Toots and the Maytals','artist':'Joe Boyd','type':'SoundManager','source':'joe-boyd-toots','outputTo':'tootsVideo','icon':'toots-front-480x460.jpg'}
			},

			// pink floyd - arnold layne
			{'title':'Arnold Layne','artist':'Pink Floyd','type':'SoundManager','source':'pink-floyd-arnold-layne','outputTo':'arnoldLayneAudio','icon':'pink-floyd-arnold-lane-front-480x460.jpg'},
			
			// bonzo dog band
			{'title':'The Intro and the Outro','artist':'Bonzo Dog Band','type':'SoundManager','source':'bonzo-dog-band-the-intro-and-the-outro','outputTo':'bonzoDogAudio','icon':'bonzo-dog-band-front-480x460.jpg'},
			
			// talking heads
			{'title':'Take Me To The River','artist':'Talking Heads','type':'SoundManager','source':'talking-heads-take-me-to-the-river','outputTo':'talkingHeadsAudio','icon':'talking-heads-sleeve.jpg'}
			
		]	
	},
	7:{
		'name':'Nick Drake',
		'author':'Pete Paphides',
		'hasVideo':false,
		'dynamic':false,
		'tracks':[
			// pink floyd
			{'title':'Bike','artist':'Pink Floyd','type':'SoundManager','source':'pink-floyd-bike','outputTo':'pinkFloydAudio','icon':'pink-floyd-piper-at-the-gates-of-dawn-sleeve.jpg'},

			// ammmusic
			{'title':'After Rapidly Circling The Plaza','artist':'AMMmusic','type':'SoundManager','source':'ammmusic-after-rapidly-circling-the-plaza','outputTo':'ammAudio','offScreen':true,'icon':'ammmusic-sleeve.jpg'},

			// the purple gang 
			{'title':'Granny Takes A Trip','artist':'The Purple Gang','type':'SoundManager','source':'the-purple-gang-granny-takes-a-trip','outputTo':'purpleAudio','offScreen':true,'icon':'the-purple-gang-strikes-sleeve.jpg'},

			// charles mingus - 
			{'title':'Fables of Faubus','artist':'Charles Mingus','type':'SoundManager','source':'charlie-mingus-fables-of-faubus','outputTo':'mingusAudio','icon':'charles-mingus-ah-um-sleeve.jpg'},
			
			// pipes of pan
			{'title':'Side A','artist':'Pipes of Pan','type':'SoundManager','source':'pipes-of-pan-side-a','outputTo':'pipesAudio','icon':'brian-jones-pipes-of-pan-sleeve.jpg'},
			{'title':'Pipes of Pan','artist':'Joe Boyd','type':'YouTube','source':'ngVsi9J2r_A','outputTo':'pipesVideo','splashImage':'joe-boyd-joujouka.jpg',
				'fallback':{'title':'Pipes of Pan','artist':'Joe Boyd','type':'SoundManager','source':'joe-boyd-pipes-of-pan','outputTo':'pipesVideo','splashImage':'brian-jones-pipes-of-pan-sleeve.jpg'}
			},
			
			// 10,000 maniacs 
			{'title':'Scorpio Rising','artist':'10,000 Maniacs','type':'SoundManager','source':'10000-maniacs-scorpio-rising','outputTo':'maniacsAudio','icon':'10000-maniacs-the-wishing-chair-sleeve.jpg'},

			// bob dylan
			{'title':'Million Dollar Bash','artist':'Bob Dylan','type':'SoundManager','source':'bob-dylan-million-dollar-bash','outputTo':'dylanAudio','icon':'bob-dylan-sleeve.jpg'},
			{'title':'Bob Dylan','artist':'Joe Boyd','type':'YouTube','source':'IN68RyVJSgw','outputTo':'dylanVideo','splashImage':'joe-boyd-bob-dylan.jpg',
				'fallback':{'title':'Bob Dylan','artist':'Joe Boyd','type':'SoundManager','source':'joe-boyd-dylan','outputTo':'dylanVideo','icon':'bob-dylan-sleeve.jpg'}
			},

			// fairport -  
			{'title':'A Sailor\'s Life','artist':'Fairport Convention','type':'SoundManager','source':'fairport-convention-a-sailors-life','outputTo':'fairportAudio','icon':'fairport-convention-unhalfbricking-sleeve.jpg'},
			{'title':'Unhalfbricking','artist':'Joe Boyd','type':'YouTube','source':'243jyoOoEVU','outputTo':'fairportVideo','splashImage':'joe-boyd-fairport.jpg',
				'fallback':{'title':'Unhalfbricking','artist':'Joe Boyd','type':'SoundManager','source':'joe-boyd-fairport','outputTo':'fairportVideo','splashImage':'record-box-logo-inverse.png'}
			},

			// nick drake
			{'title':'Day is Done','artist':'Nick Drake','type':'SoundManager','source':'nick-drake-day-is-done','outputTo':'nickDrakeAudio','icon':'nick-drake-5-leaves-left-sleeve.jpg'},
			{'title':'Five Leaves Left','artist':'Joe Boyd','type':'YouTube','source':'rOb2X8XVRuQ','outputTo':'nickDrakeVideo','splashImage':'joe-boyd-nick-drake.jpg',
				'fallback':{'title':'Five Leaves Left','artist':'Joe Boyd','type':'SoundManager','source':'joe-boyd-nick-drake','outputTo':'nickDrakeVideo'}
			},

			// geoff mulduar
			{'title':'Higher & Higher','artist':'Geoff Muldaur','type':'SoundManager','source':'geoff-mulduar-higher-and-higher','outputTo':'mulduarAudio','icon':'geoff-muldaur-sleeve.jpg'},
			
			// them / van morrison
			{'title':'Gloria','artist':'Them','type':'SoundManager','source':'them-gloria','outputTo':'themAudio','icon':'them-van-morrison-sleeve.jpg'},

			// boz scaggs
			{'title':'Dinah Flo','artist':'Boz Scaggs','type':'SoundManager','source':'boz-scaggs-dinah-flo','outputTo':'bozAudio','icon':'boz-scaggs-my-time-sleeve.jpg'},
			
			// thomas mapfumo 
			{'title':'Shumba','artist':'Thomas Mapfumo','type':'SoundManager','source':'thomas-mapfumo-shumba','outputTo':'mapfumoAudio','icon':'thomas-mapfumo-sleeve.jpg'},
			
			// moby grape 
			{'title':'Omaha','artist':'Moby Grape','type':'SoundManager','source':'moby-grape-omaha','outputTo':'mobyAudio','icon':'moby-grape-sleeve.jpg'},
			
			// lightnin hopkins 
			{'title':'Blues for Queen Elizabeth','artist':'Lightnin\' Hopkins','type':'SoundManager','source':'lightnin-hopkins-blues-for-queen-elizabeth','outputTo':'lightninAudio','icon':'sam-lightnin-hopkins-sleeve.jpg'},
			
			// rural blues
			{'title':'Shelby County Work House Blues','artist':'Hambone Willie Newburn','type':'SoundManager','source':'hambone-willie-newburn-shelby-county-work-house-blues','outputTo':'ruralAudio','icon':'rural-blues-sleeve.jpg'},
			{'title':'The Rural Blues','artist':'Joe Boyd','type':'YouTube','source':'h90s3OIgo0w','outputTo':'ruralVideo','splashImage':'joe-boyd-rural-blues.jpg',
				'fallback':{'title':'The Rural Blues','artist':'Joe Boyd','type':'SoundManager','source':'joe-boyd-rural-blues','outputTo':'ruralVideo','splashImage':'record-box-logo-inverse.png'}
			},

			// chess golden decade 
			{'title':'Sincerely','artist':'The Moonglows','type':'SoundManager','source':'the-moonglows-sincerely','outputTo':'chessAudio','icon':'chess-golden-decade-sleeve.jpg'},
			
			// denny laine 
			{'title':'Say You Don\'t Mind','artist':'Denny Laine','type':'SoundManager','source':'denny-laine-say-you-dont-mind','outputTo':'dennyAudio','offScreen':true,'icon':'denny-laine-say-you-dont-mind-front-480x460.jpg'},
			
			// tomorrow
			{'title':'My White Bicycle','artist':'Tomorrow','type':'SoundManager','source':'tomorrow-my-white-bicycle','outputTo':'tomorrowAudio','offScreen':true,'icon':'tomorrow-front-480x460.jpg'},
			
			// toots and the maytals 
			{'title':'Struggle','artist':'Toots and the Maytals','type':'SoundManager','source':'toots-and-the-maytals-struggle','outputTo':'tootsAudio','offScreen':true,'icon':'toots-front-480x460.jpg'},
			{'title':'Toots and the Maytals','artist':'Joe Boyd','type':'YouTube','source':'7EMDeiJWZgQ','outputTo':'tootsVideo','splashImage':'joe-boyd-toots.jpg',
				'fallback':{'title':'Toots and the Maytals','artist':'Joe Boyd','type':'SoundManager','source':'joe-boyd-toots','outputTo':'tootsVideo','icon':'toots-front-480x460.jpg'}
			},

			// pink floyd - arnold layne
			{'title':'Arnold Layne','artist':'Pink Floyd','type':'SoundManager','source':'pink-floyd-arnold-layne','outputTo':'arnoldLayneAudio','icon':'pink-floyd-arnold-lane-front-480x460.jpg'},
			
			// bonzo dog band
			{'title':'The Intro and the Outro','artist':'Bonzo Dog Band','type':'SoundManager','source':'bonzo-dog-band-the-intro-and-the-outro','outputTo':'bonzoDogAudio','icon':'bonzo-dog-band-front-480x460.jpg'},
			
			// talking heads
			{'title':'Take Me To The River','artist':'Talking Heads','type':'SoundManager','source':'talking-heads-take-me-to-the-river','outputTo':'talkingHeadsAudio','icon':'talking-heads-sleeve.jpg'}
		]	
	},
	8:{
		'name':'Mala Dynamic test',
		'author':'Bruce Grove',
		'hasVideo':true,
		'dynamic':true,
		'folder':'mala-dynamic',
		'tracks':[
			{'title':'East of the River Nile','artist':'Augustus Pablo','type':'SoundManager','source':'augustus-pablo-east-of-the-river-nile','outputTo':'pabloERNAudio','icon':'augustus-pablo-east-sleeve.jpg'},
			{'title':'Man Kind','artist':'Misty in Roots','type':'SoundManager','source':'misty-in-roots-man-kind','outputTo':'mistyLiveAudio','icon':'misty-in-roots-live-front.jpg'},
			{'title':'King Tubby\'s Special Mix','artist':'Augustus Pablo','type':'SoundManager','source':'augustus-pablo-king-tubbys-special-mix','outputTo':'pabloTubbyAudio','icon':'augustus-pablo-king-tubby-front.jpg'},
			{'title':'Spliffhead (Remix)','artist':'Ragga Twins','type':'SoundManager','source':'the-ragga-twins-reggae-owes-me-money-spliffhead-remix','outputTo':'raggaTwinsAudio','icon':'ragga-twins-reggae-owes-me-money-front.jpg'},
			{'title':'You Got Me Burnin\'','artist':'Cloud 9','type':'SoundManager','source':'cloud-9-you-got-me-burnin','outputTo':'cloud9Audio','icon':'cloud-9-you-got-me-burnin-front.jpg'},
			{'title':'Lord of the Null Lines','artist':'Hyper-on Experience','type':'SoundManager','source':'hyper-on-experience-lords-of-the-null-lines','outputTo':'hyperAudio','icon':'hyper-on-experience-lord-of-the-null-lines-front.jpg'},
			{'title':'Maxi','artist':'Tom and Jerry','type':'SoundManager','source':'tom-and-jerry-maxi','outputTo':'tomJerryMaxiAudio','icon':'tom-and-jerry-maxi-front.jpg'},
			{'title':'Manhattan Melody','artist':'Lemon D','type':'SoundManager','source':'lemon-d-manhattan-melody','outputTo':'lemonDManhattanAudio','icon':'lemon-d-jah-love-front.jpg'},
			
			{'title':'Antique Toy','artist':'The Future Sound of London','type':'SoundManager','source':'the-future-sound-of-london-antique-toy','outputTo':'fsolAudio','icon':'future-sound-of-london-dead-cities-front.jpg'},
			{'title':'The Future Sound of London','artist':'Mala','type':'YouTube','source':'1KNqjTLDJ_4','outputTo':'fsolVideo','splashImage':'future-sound-of-london-dead-cities-front.jpg',
				'fallback':{'title':'The Future Sound of London','artist':'Mala','type':'SoundManager','source':'mala-future-sound','outputTo':'fsolVideo','splashImage':'future-sound-of-london-dead-cities-front.jpg'}
			},
			{'title':'Digital','artist':'Goldie','type':'SoundManager','source':'goldie-digital','outputTo':'goldieDigitalAudio','icon':'goldie-digital-front.jpg'},
			{'title':'Fade to Black','artist':'Souljah','type':'SoundManager','source':'souljah-fade-to-black','outputTo':'souljahFadeAudio','icon':'souljah-fade-to-black-front.jpg'},
			{'title':'Juggle Tings Proper','artist':'Roots Manuva','type':'SoundManager','source':'roots-manuva-juggle-tings-proper','outputTo':'rootsManuvaJuggleAudio','icon':'roots-manuva-juggle-tings-proper-front.jpg'},
			
			{'title':'Red','artist':'Artwork','type':'SoundManager','source':'artwork-red','outputTo':'artworkRedAudio','icon':'artwork-red-ep-front.jpg'},
			{'title':'Artwork / Menta','artist':'Mala','type':'YouTube','source':'ODe5YWm-xRo','outputTo':'artworkRedVideo','splashImage':'artwork-red-ep-front.jpg',
				'fallback':{'title':'Artwork / Menta','artist':'Mala','type':'SoundManager','source':'mala-artwork','outputTo':'artworkRedVideo','splashImage':'artwork-red-ep-front.jpg'}
			},

			{'title':'Thriller Funk','artist':'Slaughter Mob','type':'SoundManager','source':'the-slaughter-mob-thriller-funk','outputTo':'slaughterThrillerAudio','icon':'slaughter-mob-saddam-front.jpg'},
			{'title':'Slaughter Mob','artist':'Mala','type':'YouTube','source':'_nNyAClrJOY','outputTo':'slaughterThrillerVideo','splashImage':'slaughter-mob-saddam-front.jpg',
				'fallback':{'title':'Slaughter Mob','artist':'Mala','type':'SoundManager','source':'mala-slaughter-mob','outputTo':'slaughterThrillerVideo','splashImage':'slaughter-mob-saddam-front.jpg'}
			},

			{'title':'Wot Do U Call It?','artist':'Wiley','type':'SoundManager','source':'wiley-wot-do-u-call-it','outputTo':'wileyWotAudio','icon':'wiley-wot-u-call-it-front.jpg'},
			{'title':'Wiley','artist':'Mala','type':'YouTube','source':'A0CH3ovfjCM','outputTo':'wileyWotVideo','splashImage':'wiley-wot-u-call-it-front.jpg',
				'fallback':{'title':'Sign of the Dub','artist':'Mala','type':'SoundManager','source':'mala-wiley','outputTo':'wileyWotVideo','splashImage':'wiley-wot-u-call-it-front.jpg'}
			},
			{'title':'Walking Bass','artist':'Benga','type':'SoundManager','source':'benga-walking-bass','outputTo':'bengaWalkingAudio','icon':'benga-skream-hydro-front.jpg'},{'title':'Walking Bass','artist':'Benga','type':'SoundManager','source':'benga-walking-bass','outputTo':'bengaWalkingAudio','icon':'benga-skream-hydro-front.jpg'},
			{'title':'Ugly','artist':'Digital Mystikz','type':'SoundManager','source':'digital-mystikz-ugly','outputTo':'digitalMystikzUglyAudio','icon':'digital-mystikz-pathways-front.jpg'},
			{'title':'Twisup','artist':'Loefah','type':'SoundManager','source':'digital-mystikz-twisup','outputTo':'digitalMystikzTwisupAudio','icon':'digital-mystikz-twisup-front.jpg'},
			
			{'title':'Sign of the Dub','artist':'Kode 9 & Daddi G','type':'SoundManager','source':'kode-9-and-daddi-g-sign-of-the-dub','outputTo':'kode9SignAudio','icon':'kode-9-daddi-gee-sign-of-the-dub-front.jpg'},
			{'title':'Sign of the Dub','artist':'Mala','type':'YouTube','source':'inmRPzKYwRI','outputTo':'kode9SignVideo','splashImage':'kode-9-daddi-gee-sign-of-the-dub-front.jpg',
				'fallback':{'title':'Sign of the Dub','artist':'Mala','type':'SoundManager','source':'mala-sign-of-the-dub','outputTo':'kode9SignVideo','splashImage':'kode-9-daddi-gee-sign-of-the-dub-front.jpg'}
			}
		]	
	}

};

var playlist={}; // the actual working playlist will be put into this when the page is using it. Can look at dynamically constructing this in future.


// samples for each file format