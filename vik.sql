-- -----------------------------------------------------------------------------
--             Génération d'une base de données pour
--                      Oracle Version '10g
--                     ( 14/3/2026 16:12:07)
-- -----------------------------------------------------------------------------
--      Nom de la base : MLR1
--      Projet : VikingTransport
--      Auteur : EricPorcq
--      Date de dernière modification : 13/3/2026 18:07:49
-- -----------------------------------------------------------------------------

DROP TABLE VIK_DEPARTEMENT CASCADE CONSTRAINTS;
DROP TABLE VIK_TYPE_CLIENT CASCADE CONSTRAINTS;
DROP TABLE VIK_TARIF CASCADE CONSTRAINTS;
DROP TABLE VIK_COMMUNE CASCADE CONSTRAINTS;
DROP TABLE VIK_LIGNE CASCADE CONSTRAINTS;
DROP TABLE VIK_CLIENT CASCADE CONSTRAINTS;
DROP TABLE VIK_REDUCTION CASCADE CONSTRAINTS;
DROP TABLE vik_reservation CASCADE CONSTRAINTS;
DROP TABLE VIK_ETAPE CASCADE CONSTRAINTS;

CREATE TABLE VIK_DEPARTEMENT
( 
    DEP_NUM CHAR(3)  NOT NULL,
    DEP_NOM VARCHAR2(32)  NULL,
	CONSTRAINT PK_VIK_DEPARTEMENT PRIMARY KEY (DEP_NUM)  
);

CREATE TABLE VIK_TYPE_CLIENT
( 
    TYP_NUM NUMBER(10)  NOT NULL,
    TYP_NOM VARCHAR2(32)  NULL,
    TYP_PT_LIMITE NUMBER( 6)  NULL,
    TYP_REDUC NUMBER(2)  NULL,
	CONSTRAINT PK_VIK_TYPE_CLIENT PRIMARY KEY (TYP_NUM)  
);

CREATE TABLE VIK_TARIF
( 
    TAR_NUM_TRANCHE NUMBER(2)  NOT NULL,
    TAR_MIN_DIST NUMBER(4,1)  NULL,
    TAR_MAX_DIST NUMBER(4,1)  NULL,
    TAR_PRIX NUMBER(4)  NULL,
	CONSTRAINT PK_VIK_TARIF PRIMARY KEY (TAR_NUM_TRANCHE)  
);

CREATE TABLE VIK_COMMUNE
( 
    COM_CODE_INSEE CHAR(5)  NOT NULL,
    DEP_NUM CHAR(3)  NOT NULL,
    COM_NOM VARCHAR2(64)  NULL,
    COM_POP NUMBER(6)  NULL,
	CONSTRAINT PK_VIK_COMMUNE PRIMARY KEY (COM_CODE_INSEE)  
);

CREATE  INDEX I_FK_VIK_COMMUNE_VIK_DEPARTEME
     ON VIK_COMMUNE (DEP_NUM ASC)
;

CREATE TABLE VIK_LIGNE
( 
    LIG_NUM CHAR(3)  NOT NULL,
    COM_CODE_INSEE_DEBU CHAR(5)  NOT NULL,
    COM_CODE_INSEE_TERM CHAR(5)  NOT NULL,
	CONSTRAINT PK_VIK_LIGNE PRIMARY KEY (LIG_NUM)  
);

CREATE  INDEX I_FK_VIK_LIGNE_VIK_COMMUNE
     ON VIK_LIGNE (COM_CODE_INSEE_DEBU ASC);

CREATE  INDEX I_FK_VIK_LIGNE_VIK_COMMUNE2
     ON VIK_LIGNE (COM_CODE_INSEE_TERM ASC);

CREATE TABLE VIK_CLIENT
( 
    CLI_NUM NUMBER(3)  NOT NULL,
    TYP_NUM NUMBER(10)  NULL,
    DEP_NUM CHAR(3)  NULL,
    CLI_NOM VARCHAR2(64)  NULL,
    CLI_PRENOM VARCHAR2(64)  NULL,
    CLI_VILLE VARCHAR2(64)  NULL,
    CLI_TELEPHONE VARCHAR2( 16)  NULL,
    CLI_COURRIEL VARCHAR2(32)  NULL,
    CLI_NB_POINTS_EC NUMBER(4)  NULL,
    CLI_NB_POINTS_TOT NUMBER(6)  NULL,
    CLI_DATE_CONNEC DATE  NULL,
	CONSTRAINT PK_VIK_CLIENT PRIMARY KEY (CLI_NUM)  
);

CREATE  INDEX I_FK_VIK_CLIENT_VIK_TYPE_CLIEN
     ON VIK_CLIENT (TYP_NUM ASC);

CREATE  INDEX I_FK_VIK_CLIENT_VIK_DEPARTEMEN
     ON VIK_CLIENT (DEP_NUM ASC);

CREATE TABLE VIK_REDUCTION
( 
    RED_NB_POINTS NUMBER(4)  NOT NULL,
    RED_VALEUR NUMBER(2)  NULL,
	CONSTRAINT PK_VIK_REDUCTION PRIMARY KEY ( RED_NB_POINTS)  
);

CREATE TABLE vik_reservation
( 
    CLI_NUM NUMBER(3)  NOT NULL,
    RES_NUM NUMBER(4)  NOT NULL,
    TAR_NUM_TRANCHE NUMBER(2)  NOT NULL,
    RES_DATE DATE  NULL,
    RES_NB_POINTS NUMBER(3)  NULL,
    RES_PRIX_TOT NUMBER(5)  NULL,
	CONSTRAINT PK_vik_reservation PRIMARY KEY (CLI_NUM, RES_NUM)  
);

CREATE  INDEX I_FK_vik_reservation_VIK_TARIF
     ON vik_reservation (TAR_NUM_TRANCHE ASC);
	 
CREATE  INDEX I_FK_vik_reservation_VIK_CLIEN
     ON vik_reservation (CLI_NUM ASC);

CREATE TABLE VIK_ETAPE
( 
    LIG_NUM CHAR(3)  NOT NULL,
    CLI_NUM NUMBER(3)  NOT NULL,
    RES_NUM NUMBER(4)  NOT NULL,
    COM_CODE_INSEE_DEPART CHAR(5)  NOT NULL,
    COM_CODE_INSEE_ARRIVEE CHAR(5)  NOT NULL,
    ETA_DISTANCE NUMBER(4,1)  NULL,
    ETA_HEURE DATE  NULL,
	CONSTRAINT PK_VIK_ETAPE PRIMARY KEY (CLI_NUM, RES_NUM, LIG_NUM, COM_CODE_INSEE_DEPART, COM_CODE_INSEE_ARRIVEE)  
);



CREATE  INDEX I_FK_VIK_ETAPE_VIK_LIGNE
     ON VIK_ETAPE (LIG_NUM ASC);

CREATE  INDEX I_FK_VIK_ETAPE_vik_reservation
     ON VIK_ETAPE (CLI_NUM ASC, RES_NUM ASC);

CREATE  INDEX I_FK_VIK_ETAPE_VIK_COMMUNE
     ON VIK_ETAPE (COM_CODE_INSEE_ARRIVEE ASC);

CREATE  INDEX I_FK_VIK_ETAPE_VIK_COMMUNE1
     ON VIK_ETAPE (COM_CODE_INSEE_DEPART ASC);

CREATE  INDEX I_FK_VIK_NOEUD_VIK_LIGNE
     ON VIK_NOEUD (LIG_NUM ASC);

CREATE  INDEX I_FK_VIK_NOEUD_VIK_COMMUNE
     ON VIK_NOEUD (COM_CODE_INSEE_ARRET ASC);

CREATE  INDEX I_FK_VIK_NOEUD_VIK_COMMUNE1
     ON VIK_NOEUD ( COM_CODE_INSEE_SUIVANT ASC);

ALTER TABLE VIK_COMMUNE ADD ( 
     CONSTRAINT FK_VIK_COMMUNE_VIK_DEPARTEMENT
          FOREIGN KEY (DEP_NUM)
               REFERENCES VIK_DEPARTEMENT (DEP_NUM));

ALTER TABLE VIK_LIGNE ADD ( 
     CONSTRAINT FK_VIK_LIGNE_VIK_COMMUNE
          FOREIGN KEY (COM_CODE_INSEE_DEBU)
               REFERENCES VIK_COMMUNE (COM_CODE_INSEE));

ALTER TABLE VIK_LIGNE ADD ( 
     CONSTRAINT FK_VIK_LIGNE_VIK_COMMUNE2
          FOREIGN KEY (COM_CODE_INSEE_TERM)
               REFERENCES VIK_COMMUNE (COM_CODE_INSEE));

ALTER TABLE VIK_CLIENT ADD ( 
     CONSTRAINT FK_VIK_CLIENT_VIK_TYPE_CLIENT
          FOREIGN KEY (TYP_NUM)
               REFERENCES VIK_TYPE_CLIENT (TYP_NUM));

ALTER TABLE VIK_CLIENT ADD ( 
     CONSTRAINT FK_VIK_CLIENT_VIK_DEPARTEMENT
          FOREIGN KEY (DEP_NUM)
               REFERENCES VIK_DEPARTEMENT (DEP_NUM));

ALTER TABLE vik_reservation ADD ( 
     CONSTRAINT FK_vik_reservation_VIK_TARIF
          FOREIGN KEY (TAR_NUM_TRANCHE)
               REFERENCES VIK_TARIF (TAR_NUM_TRANCHE));

ALTER TABLE vik_reservation ADD ( 
     CONSTRAINT FK_vik_reservation_VIK_CLIENT
          FOREIGN KEY ( CLI_NUM)
               REFERENCES VIK_CLIENT (CLI_NUM));

ALTER TABLE VIK_ETAPE ADD ( 
     CONSTRAINT FK_VIK_ETAPE_VIK_LIGNE
          FOREIGN KEY (LIG_NUM)
               REFERENCES VIK_LIGNE (LIG_NUM));

ALTER TABLE VIK_ETAPE ADD ( 
     CONSTRAINT FK_VIK_ETAPE_vik_reservation
          FOREIGN KEY ( CLI_NUM, RES_NUM)
               REFERENCES vik_reservation (CLI_NUM, RES_NUM));

ALTER TABLE VIK_ETAPE ADD ( 
     CONSTRAINT FK_VIK_ETAPE_VIK_COMMUNE
          FOREIGN KEY (COM_CODE_INSEE_ARRIVEE)
               REFERENCES VIK_COMMUNE (COM_CODE_INSEE));

ALTER TABLE VIK_ETAPE ADD ( 
     CONSTRAINT FK_VIK_ETAPE_VIK_COMMUNE1
          FOREIGN KEY (COM_CODE_INSEE_DEPART)
               REFERENCES VIK_COMMUNE (COM_CODE_INSEE));

-- -----------------------------------------------------------------------------
--                FIN DE GENERATION
-- -----------------------------------------------------------------------------

insert into vik_reduction values ( 100  , 1   );
insert into vik_reduction values ( 500  , 7   );
insert into vik_reduction values ( 1000 , 15  );

insert into vik_departement values ( 14 , 'Calvados'       );
insert into vik_departement values ( 50 , 'Manche'         );
insert into vik_departement values ( 61 , 'Orne'           );
insert into vik_departement values ( 27 , 'Eure'           );
insert into vik_departement values ( 76 , 'Seine Maritime' );
insert into vik_departement values ( 28 , 'Eure et Loire'  );
insert into vik_departement values ( 72 , 'Sarthe'         );
insert into vik_departement values ( 78 , 'Yvelines'       );
insert into vik_departement values ( 80 , 'Somme'          );

insert into vik_type_client values ( 1 , 'Nouveau'    , 10     , 95 );
insert into vik_type_client values ( 2 , 'poussin'    , 400    , 90 );
insert into vik_type_client values ( 3 , 'junior'     , 3000   , 80 );
insert into vik_type_client values ( 4 , 'argent'     , 10000  , 65 );
insert into vik_type_client values ( 5 , 'or'         , 50000  , 50 );

insert into vik_client values ( '0'   , ''  , ''   , 'NON INSCRIT'  , ''          , ''           , ''              , ''                         , '0'   , '0'     , ''          );
insert into vik_client values ( '4'   , '1' , '14' , 'MARIE'        , 'Veronique' , 'Caen'       , ''              , 'v.marie@gmal.com'         , '5'   , '5'     , to_date('16/01/2023','dd/mm/yyyy') );
insert into vik_client values ( '5'   , '2' , '14' , 'LE BOURGEOIS' , 'Bernard'   , 'Caen'       , '0033231112118' ,'nanard@orage.fr'           , '35'  , '35'    , to_date('13/12/2022','dd/mm/yyyy') );
insert into vik_client values ( '6'   , '2' , '50' , 'SMITH'        , 'Tom'       , 'Cherbourg'  , ''              , 'tom.smith43@free.uk'      , '46'  , '46'    , to_date('15/03/2023','dd/mm/yyyy') );
insert into vik_client values ( '7'   , '2' , '14' , 'MARIE'        , 'Josiane'   , 'Ifs'        , '0033621112112' , 'j.marie@frie.fr'          , '25'  , '35'    , to_date('13/01/2023','dd/mm/yyyy') );
insert into vik_client values ( '8'   , '2' , '14' , 'DURAND'       , 'Paul'      , 'Ifs'        , '0033231202020' , 'paul.durand@wanadoo.fr'   , '38'  , '58'    , to_date('28/03/2023','dd/mm/yyyy') );
insert into vik_client values ( '9'   , '2' , '61' , 'POULEQ'       , 'Patrick'   , 'Flers'      , '0033612202021' , ''                         , '106' , '306'   , to_date('16/03/2023','dd/mm/yyyy') );
insert into vik_client values ( '10'  , '3' , '61' , 'NEIGE'        , 'Blanche'   , 'Flers'      , '0033612202022' , ''                         , '9'   , '3410'  , to_date('10/03/2023','dd/mm/yyyy') );
insert into vik_client values ( '11'  , '3' , '61' , 'ESCARRE'      , 'Stéphane'  , 'Flers'      , ''              , 'sescarre@wanadogou.ke'    , '95'  , '3795'  , to_date('09/04/2023','dd/mm/yyyy') );
insert into vik_client values ( '13'  , '1' , '50' , 'SUPORMOI'     , 'Sylvie'    , 'Cherbourg'  , ''              , ''                         , '8'   , '8'     , to_date('14/08/2022','dd/mm/yyyy') );
insert into vik_client values ( '16'  , '2' , '14' , 'LITON'        , 'Samir'     , 'Ifs'        , '0033123888456' , 'samirliton@orage.fr'      , '24'  , '44'    , to_date('15/10/2022','dd/mm/yyyy') );
insert into vik_client values ( '18'  , '2' , '14' , 'DURAND'       , 'Simone'    , 'Ifs'        , '0033729113115' , 'simone.durand@gmal.com'   , '65'  , '65'    , to_date('13/02/2023','dd/mm/yyyy') );
insert into vik_client values ( '20'  , '2' , '14' , 'JORT'         , 'Etama'     , 'Ifs'        , '0033623524456' , 'etamajort@frie.fr'        , '46'  , '246'   , to_date('11/04/2023','dd/mm/yyyy') );
insert into vik_client values ( '24'  , '5' , '14' , 'VERGLASSEE'   , 'Ruth'      , 'Ifs'        , '0033729113116' , 'rverglassee@gmal.com'     , '55'  , '58000' , to_date('13/10/2022','dd/mm/yyyy') );
insert into vik_client values ( '26'  , '3' , '14' , 'GZAGUEE'      , 'Suzie'     , 'Caen'       , '0033729403110' , 'suzie14@gmal.com'         , '9'   , '4908'  , to_date('16/02/2023','dd/mm/yyyy') );
insert into vik_client values ( '27'  , '4' , '76' , 'NAGE'         , 'Icare'     , 'Rouen'      , '0033619003100' , 'icarenage@gmal.com'       , '46'  , '13506' , to_date('05/04/2023','dd/mm/yyyy') );
insert into vik_client values ( '31'  , '1' , '14' , 'SUPLONG'      , 'Arthur'    , 'Boulon'     , '0033666004007' , 'supdeboulon@gmal.com'     , '2'   , '2'     , to_date('05/04/2023','dd/mm/yyyy') );
insert into vik_client values ( '33'  , '2' , '50' , 'TALUT'        , 'Jean'      , 'Cherbourg'  , ''              , '4jeantalut@gmal.com'      , '81'  , '198'   , to_date('13/04/2023','dd/mm/yyyy') );
insert into vik_client values ( '35'  , '3' , '50' , 'DEWAERE'      , 'Marlène'   , 'Valognes'   , ''              , 'marlenedewaere@gmal.com'  , '95'  , '3505'  , to_date('12/04/2023','dd/mm/yyyy') );
insert into vik_client values ( '38'  , '5' , '50' , 'RIVES'        , 'Chiquita'  , 'Taillepied' , '0033688241309' , 'ChiquitaRives@gmal.com'   , '0'   , '58000' , to_date('15/10/2022','dd/mm/yyyy') );
insert into vik_client values ( '40'  , '1' , '61' , 'POIGNON'      , 'Vincent'   , 'Flers'      , ''              , ''                         , '0'   , '0'     , to_date('01/05/2022','dd/mm/yyyy') );
insert into vik_client values ( '41'  , '1' , '61' , 'DE THUNE'     , 'Aplu'      , 'Alençon'    , ''              , ''                         , '0'   , '0'     , to_date('01/04/2022','dd/mm/yyyy') );
insert into vik_client values ( '42'  , '1' , '14' , 'CANTE'        , 'Hermine'   , 'Caen'       , ''              , ''                         , '0'   , '0'     , to_date('01/09/2022','dd/mm/yyyy') );
insert into vik_client values ( '101' , '1' , '14' , 'DUPONT'       , 'Luc'       , 'Caen'       , '0600000001'    , 'luc.dupont@mail.fr'       , '5'   , '120'   , to_date('10/03/2026','dd/mm/yyyy') );
insert into vik_client values ( '102' , '2' , '76' , 'LEMAIRE'      , 'Sophie'    , 'Rouen'      , '0600000002'    , 'sophie.lemaire@mail.fr'   , '10'  , '210'   , to_date('12/03/2026','dd/mm/yyyy') );
insert into vik_client values ( '103' , '1' , '14' , 'CARON'        , 'Paul'      , 'Lisieux'    , '0600000003'    , 'paul.caron@mail.fr'       , '0'   , '15'    , to_date('08/03/2026','dd/mm/yyyy') );
insert into vik_client values ( '104' , '1' , '61' , 'LEFEBVRE'     , 'Claire'    , 'Flers'      , '0600000004'    , 'claire.lefebvre@mail.fr'  , '3'   , '60'    , to_date('09/03/2026','dd/mm/yyyy') );
insert into vik_client values ( '105' , '2' , '50' , 'MARTIN'       , 'Thomas'    , 'Granville'  , '0600000005'    , 'thomas.martin@mail.fr'    , '8'   , '190'   , to_date('07/03/2026','dd/mm/yyyy') );
insert into vik_client values ( '106' , '1' , '14' , 'ROBERT'       , 'Julie'     , 'Vire'       , '0600000006'    , 'julie.robert@mail.fr'     , '2'   , '30'    , to_date('06/03/2026','dd/mm/yyyy') );
insert into vik_client values ( '107' , '1' , '14' , 'PETIT'        , 'Antoine'   , 'Bayeux'     , '0600000007'    , 'antoine.petit@mail.fr'    , '4'   , '90'    , to_date('11/03/2026','dd/mm/yyyy') );
insert into vik_client values ( '108' , '1' , '61' , 'GIRARD'       , 'Camille'   , 'Argentan'   , '0600000008'    , 'camille.girard@mail.fr'   , '1'   , '25'    , to_date('05/03/2026','dd/mm/yyyy') );
insert into vik_client values ( '109' , '2' , '50' , 'ROUSSEAU'     , 'Nicolas'   , 'Cherbourg'  , '0600000009'    , 'nicolas.rousseau@mail.fr' , '7'   , '150'   , to_date('13/03/2026','dd/mm/yyyy') );
insert into vik_client values ( '110' , '2' , '50' , 'MOREAU'       , 'Emma'      , 'Avranches'  , '0600000010'    , 'emma.moreau@mail.fr'      , '6'  , '140'    , to_date('12/03/2026','dd/mm/yyyy') );

insert into vik_tarif values ( 1  ,  0  , 10  , 5  );
insert into vik_tarif values ( 2  , 11  , 20  , 7  );
insert into vik_tarif values ( 3  , 21  , 30  , 10 );
insert into vik_tarif values ( 4  , 31  , 40  , 12 );
insert into vik_tarif values ( 5  , 41  , 50  , 16 );
insert into vik_tarif values ( 6  , 51  , 60  , 20 );
insert into vik_tarif values ( 7  , 61  , 80  , 30 );
insert into vik_tarif values ( 8  , 81  , 100 , 40 );
insert into vik_tarif values ( 9  , 101 , 140 , 50 );
insert into vik_tarif values ( 10 , 141 , 160 , 60 );
insert into vik_tarif values ( 11 , 161 , 200 , 70 );
insert into vik_tarif values ( 12 , 201 , 300 , 80 );
insert into vik_tarif values ( 13 , 301 , 500 , 90 );

insert into vik_commune values ( '61001' , '61' , 'Alençon'                        , '25744'  );
insert into vik_commune values ( '61006' , '61' , 'Argentan'                       , '13401'  );
insert into vik_commune values ( '14027' , '14' , 'Aunay-sur-Odon'                 , '3192'   );
insert into vik_commune values ( '50025' , '50' , 'Avranches'                      , '10277'  );
insert into vik_commune values ( '61483' , '61' , 'Bagnoles de l''Orne Normandie'  , '3100'   );
insert into vik_commune values ( '50031' , '50' , 'Barneville-Carteret'            , '2214'   );
insert into vik_commune values ( '14047' , '14' , 'Bayeux'                         , '12640'  );
insert into vik_commune values ( '27051' , '27' , 'Beaumont-le-Roger'              , '2791'   );
insert into vik_commune values ( '61038' , '61' , 'Bellême'                        , '2371'   );
insert into vik_commune values ( '27056' , '27' , 'Bernay'                         , '9654'   );
insert into vik_commune values ( '76114' , '76' , 'Bolbec'                         , '11551'  );
insert into vik_commune values ( '78107' , '78' , 'Bréval'                         , '1901'   );
insert into vik_commune values ( '61063' , '61' , 'Briouze'                        , '1510'   );
insert into vik_commune values ( '76146' , '76' , 'Buchy'                          , '2820'   );
insert into vik_commune values ( '14118' , '14' , 'Caen'                           , '107250' );
insert into vik_commune values ( '50099' , '50' , 'Carentan-les-Marais'            , '10227'  );
insert into vik_commune values ( '14143' , '14' , 'Caumont-l''Éventé'              , '1346'   );
insert into vik_commune values ( '50129' , '50' , 'Cherbourg-en-Cotentin'          , '77789'  );
insert into vik_commune values ( '14174' , '14' , 'Condé-en-Normandie'             , '6296'   );
insert into vik_commune values ( '14191' , '14' , 'Courseulles-sur-Mer'            , '4185'   );
insert into vik_commune values ( '50147' , '50' , 'Coutances'                      , '8380'   );
insert into vik_commune values ( '14220' , '14' , 'Deauville'                      , '3567'   );
insert into vik_commune values ( '76217' , '76' , 'Dieppe'                         , '35233'  );
insert into vik_commune values ( '61700' , '61' , 'Domfront en Poiraie'            , '4180'   );
insert into vik_commune values ( '28134' , '28' , 'Dreux'                          , '30744'  );
insert into vik_commune values ( '76231' , '76' , 'Elbeuf'                         , '16087'  );
insert into vik_commune values ( '27218' , '27' , 'Epaignes'                       , '1561'   );
insert into vik_commune values ( '27229' , '27' , 'Évreux'                         , '46869'  );
insert into vik_commune values ( '14258' , '14' , 'Falaise'                        , '7849'   );
insert into vik_commune values ( '76259' , '76' , 'Fécamp'                         , '18054'  );
insert into vik_commune values ( '50184' , '50' , 'Flamanville'                    , '1767'   );
insert into vik_commune values ( '61169' , '61' , 'Flers'                          , '14589'  );
insert into vik_commune values ( '61181' , '61' , 'Gacé'                           , '1796'   );
insert into vik_commune values ( '27275' , '27' , 'Gaillon'                        , '6833'   );
insert into vik_commune values ( '80373' , '80' , 'Gamaches'                       , '2461'   );
insert into vik_commune values ( '50196' , '50' , 'Gatteville-le-Phare'            , '483'    );
insert into vik_commune values ( '27284' , '27' , 'Gisors'                         , '11863'  );
insert into vik_commune values ( '76312' , '76' , 'Gournay-en-Bray'                , '6027'   );
insert into vik_commune values ( '14312' , '14' , 'Grandcamp-Maisy'                , '1533'   );
insert into vik_commune values ( '50218' , '50' , 'Granville'                      , '12558'  );
insert into vik_commune values ( '14333' , '14' , 'Honfleur'                       , '6742'   );
insert into vik_commune values ( '61214' , '61' , 'L''Aigle'                       , '7903'   );
insert into vik_commune values ( '27309' , '27' , 'L''habit'                       , '502'    );
insert into vik_commune values ( '61168' , '61' , 'La Ferté Macé'                  , '5138'   );
insert into vik_commune values ( '50041' , '50' , 'La Hague'                       , '11257'  );
insert into vik_commune values ( '76351' , '76' , 'Le Havre'                       , '165830' );
insert into vik_commune values ( '50353' , '50' , 'Le Mont-Saint-Michel'           , '27'     );
insert into vik_commune values ( '76711' , '76' , 'Le Tréport'                     , '4441'   );
insert into vik_commune values ( '14366' , '14' , 'Lisieux'                        , '19755'  );
insert into vik_commune values ( '14367' , '14' , 'Lison'                          , '437'    );
insert into vik_commune values ( '76392' , '76' , 'Londinières'                    , '1253'   );
insert into vik_commune values ( '27375' , '27' , 'Louviers'                       , '18636'  );
insert into vik_commune values ( '72180' , '72' , 'Mamers'                         , '5082'   );
insert into vik_commune values ( '27198' , '27' , 'Mesnils-sur-Iton'               , '6149'   );
insert into vik_commune values ( '14431' , '14' , 'Mézidon-Canon'                  , '4779'   );
insert into vik_commune values ( '61293' , '61' , 'Mortagne-au-Perche'             , '3758'   );
insert into vik_commune values ( '14456' , '14' , 'Moult-Chicheboville'            , '3444'   );
insert into vik_commune values ( '76462' , '76' , 'Neufchâtel-en-Bray'             , '4646'   );
insert into vik_commune values ( '28280' , '28' , 'Nogent-le-Rotrou'               , '9433'   );
insert into vik_commune values ( '14478' , '14' , 'Orbec'                          , '1943'   );
insert into vik_commune values ( '27448' , '27' , 'Pacy-sur-Eure'                  , '5042'   );
insert into vik_commune values ( '50394' , '50' , 'Périers'                        , '2244'   );
insert into vik_commune values ( '76618' , '76' , 'Petit-Caux'                     , '9654'   );
insert into vik_commune values ( '27467' , '27' , 'Pont-Audemer'                   , '9891'   );
insert into vik_commune values ( '14514' , '14' , 'Pont-l''Évêque'                 , '4790'   );
insert into vik_commune values ( '76540' , '76' , 'Rouen'                          , '114187' );
insert into vik_commune values ( '50502' , '50' , 'Saint-Lô'                       , '19206'  );
insert into vik_commune values ( '14654' , '14' , 'Saint-Pierre-en-Auge'           , '7325'   );
insert into vik_commune values ( '76655' , '76' , 'Saint-Valery-en-Caux'           , '3906'   );
insert into vik_commune values ( '61464' , '61' , 'Sées'                           , '4199'   );
insert into vik_commune values ( '50587' , '50' , 'Taillepied'                     , '16'     );
insert into vik_commune values ( '61486' , '61' , 'Tinchebray-Bocage'              , '4896'   );
insert into vik_commune values ( '76700' , '76' , 'Tôtes'                          , '1576'   );
insert into vik_commune values ( '27701' , '27' , 'Val-de-Reuil'                   , '12702'  );
insert into vik_commune values ( '50615' , '50' , 'Valognes'                       , '6802'   );
insert into vik_commune values ( '27679' , '27' , 'Verneuil d''Avre et d''Iton'    , '7374'   );
insert into vik_commune values ( '27681' , '27' , 'Vernon'                         , '24056'  );
insert into vik_commune values ( '50639' , '50' , 'Villedieu-les-Poêles-Rouffigny' , '3849'   );
insert into vik_commune values ( '61508' , '61' , 'Vimoutiers'                     , '3096'   );
insert into vik_commune values ( '14762' , '14' , 'Vire Normandie'                 , '16935'  );
insert into vik_commune values ( '76758' , '76' , 'Yvetot'                         , '11280'  );

insert into vik_ligne values ( '1A'  , '14118' , '50041' );
insert into vik_ligne values ( '2A'  , '14118' , '50129' );
insert into vik_ligne values ( '3A'  , '14118' , '50196' );
insert into vik_ligne values ( '4A'  , '14118' , '14762' );
insert into vik_ligne values ( '5A'  , '50184' , '50353' );
insert into vik_ligne values ( '6A'  , '14118' , '50218' );
insert into vik_ligne values ( '7A'  , '14118' , '80373' );
insert into vik_ligne values ( '8A'  , '14118' , '50353' );
insert into vik_ligne values ( '9A'  , '14118' , '27681' );
insert into vik_ligne values ( '10A' , '14118' , '61700' );
insert into vik_ligne values ( '11A' , '76351' , '61001' );
insert into vik_ligne values ( '12A' , '28280' , '61001' );
insert into vik_ligne values ( '13A' , '14366' , '76540' );
insert into vik_ligne values ( '14A' , '14366' , '76146' );
insert into vik_ligne values ( '15A' , '27284' , '76540' );
insert into vik_ligne values ( '16A' , '14366' , '76655' );
insert into vik_ligne values ( '17A' , '14118' , '27284' );
insert into vik_ligne values ( '18A' , '76351' , '61464' );
insert into vik_ligne values ( '19A' , '14220' , '61001' );
insert into vik_ligne values ( '1B'  , '50041' , '14118' );
insert into vik_ligne values ( '2B'  , '50129' , '14118' );
insert into vik_ligne values ( '3B'  , '50129' , '14118' );
insert into vik_ligne values ( '4B'  , '14762' , '14118' );
insert into vik_ligne values ( '5B'  , '50353' , '50184' );
insert into vik_ligne values ( '6B'  , '50218' , '14118' );
insert into vik_ligne values ( '7B'  , '80373' , '14118' );
insert into vik_ligne values ( '8B'  , '50353' , '14118' );
insert into vik_ligne values ( '9B'  , '27681' , '14118' );
insert into vik_ligne values ( '10B' , '61700' , '14118' );
insert into vik_ligne values ( '11B' , '61001' , '76351' );
insert into vik_ligne values ( '12B' , '61001' , '28280' );
insert into vik_ligne values ( '13B' , '76540' , '14366' );
insert into vik_ligne values ( '14B' , '76146' , '14366' );
insert into vik_ligne values ( '15B' , '76540' , '27284' );
insert into vik_ligne values ( '16B' , '76655' , '14366' );
insert into vik_ligne values ( '17B' , '27284' , '14118' );
insert into vik_ligne values ( '18B' , '61464' , '76351' );
insert into vik_ligne values ( '19B' , '61001' , '14220' );

insert into vik_reservation values ( '4'   , '1'  , '1'  , to_date('12/01/2023','dd/mm/yyyy') , '1'  , '5'   );
insert into vik_reservation values ( '5'   , '3'  , '3'  , to_date('10/12/2022','dd/mm/yyyy') , '2'  , '9'   );
insert into vik_reservation values ( '6'   , '3'  , '4'  , to_date('01/01/2023','dd/mm/yyyy') , '3'  , '12'  );
insert into vik_reservation values ( '7'   , '3'  , '7'  , to_date('15/10/2022','dd/mm/yyyy') , '6'  , '30'  );
insert into vik_reservation values ( '8'   , '3'  , '8'  , to_date('15/10/2022','dd/mm/yyyy') , '8'  , '25'  );
insert into vik_reservation values ( '9'   , '5'  , '2'  , to_date('13/05/2022','dd/mm/yyyy') , '4'  , '0'   );
insert into vik_reservation values ( '10'  , '6'  , '10' , to_date('01/10/2022','dd/mm/yyyy') , '14' , '45'  );
insert into vik_reservation values ( '11'  , '7'  , '9'  , to_date('14/05/2023','dd/mm/yyyy') , '12' , '50'  );
insert into vik_reservation values ( '13'  , '1'  , '3'  , to_date('16/07/2022','dd/mm/yyyy') , '2'  , '10'  );
insert into vik_reservation values ( '16'  , '3'  , '10' , to_date('10/05/2022','dd/mm/yyyy') , '15' , '60'  );
insert into vik_reservation values ( '18'  , '5'  , '6'  , to_date('10/04/2023','dd/mm/yyyy') , '5'  , '13'  );
insert into vik_reservation values ( '20'  , '3'  , '6'  , to_date('25/05/2023','dd/mm/yyyy') , '5'  , '20'  );
insert into vik_reservation values ( '24'  , '19' , '12' , to_date('16/06/2022','dd/mm/yyyy') , '39' , '65'  );
insert into vik_reservation values ( '26'  , '7'  , '8'  , to_date('10/05/2022','dd/mm/yyyy') , '9'  , '40'  );
insert into vik_reservation values ( '27'  , '10' , '9'  , to_date('01/03/2022','dd/mm/yyyy') , '10' , '50'  );
insert into vik_reservation values ( '31'  , '2'  , '7'  , to_date('10/05/2023','dd/mm/yyyy') , '6'  , '30'  );
insert into vik_reservation values ( '33'  , '5'  , '6'  , to_date('05/04/2023','dd/mm/yyyy') , '5'  , '19'  );
insert into vik_reservation values ( '35'  , '8'  , '6'  , to_date('18/09/2022','dd/mm/yyyy') , '5'  , '20'  );
insert into vik_reservation values ( '27'  , '11' , '9'  , to_date('08/03/2022','dd/mm/yyyy') , '10' , '50'  );
insert into vik_reservation values ( '10'  , '7'  , '10' , to_date('15/10/2022','dd/mm/yyyy') , '14' , '60'  );
insert into vik_reservation values ( '11'  , '8'  , '11' , to_date('16/02/2023','dd/mm/yyyy') , '19' , '60'  );
insert into vik_reservation values ( '38'  , '9'  , '13' , to_date('10/09/2022','dd/mm/yyyy') , '44' , '90'  );
insert into vik_reservation values ( '0'   , '36' , '7'  , to_date('15/10/2022','dd/mm/yyyy') , '6'  , '30'  );
insert into vik_reservation values ( '0'   , '37' , '1'  , to_date('13/03/2023','dd/mm/yyyy') , '1'  , '5'   );
insert into vik_reservation values ( '0'   , '38' , '3'  , to_date('15/12/2022','dd/mm/yyyy') , '2'  , '9'   );
insert into vik_reservation values ( '101' , '1'  , '2'  , to_date('10/04/2026','dd/mm/yyyy') , '12' , '42'  );
insert into vik_reservation values ( '101' , '2'  , '3'  , to_date('18/04/2026','dd/mm/yyyy') , '20' , '70'  );
insert into vik_reservation values ( '101' , '3'  , '2'  , to_date('27/04/2026','dd/mm/yyyy') , '14' , '48'  );
insert into vik_reservation values ( '102' , '1'  , '3'  , to_date('11/04/2026','dd/mm/yyyy') , '24' , '80'  );
insert into vik_reservation values ( '102' , '2'  , '2'  , to_date('17/04/2026','dd/mm/yyyy') , '11' , '40'  );
insert into vik_reservation values ( '102' , '3'  , '2'  , to_date('28/04/2026','dd/mm/yyyy') , '9'  , '33'  );
insert into vik_reservation values ( '103' , '1'  , '1'  , to_date('10/04/2026','dd/mm/yyyy') , '6'  , '20'  );
insert into vik_reservation values ( '103' , '2'  , '1'  , to_date('19/04/2026','dd/mm/yyyy') , '7'  , '25'  );
insert into vik_reservation values ( '103' , '3'  , '1'  , to_date('29/04/2026','dd/mm/yyyy') , '6'  , '21'  );
insert into vik_reservation values ( '104' , '1'  , '2'  , to_date('14/04/2026','dd/mm/yyyy') , '14' , '50'  );
insert into vik_reservation values ( '104' , '2'  , '2'  , to_date('20/04/2026','dd/mm/yyyy') , '12' , '44'  );
insert into vik_reservation values ( '104' , '3'  , '2'  , to_date('30/04/2026','dd/mm/yyyy') , '13' , '46'  );
insert into vik_reservation values ( '105' , '1'  , '3'  , to_date('13/04/2026','dd/mm/yyyy') , '22' , '75'  );
insert into vik_reservation values ( '105' , '2'  , '3'  , to_date('22/04/2026','dd/mm/yyyy') , '26' , '88'  );
insert into vik_reservation values ( '105' , '3'  , '3'  , to_date('01/05/2026','dd/mm/yyyy') , '24' , '82'  );
insert into vik_reservation values ( '106' , '1'  , '1'  , to_date('11/04/2026','dd/mm/yyyy') , '5'  , '18'  );
insert into vik_reservation values ( '106' , '2'  , '1'  , to_date('21/04/2026','dd/mm/yyyy') , '4'  , '16'  );
insert into vik_reservation values ( '106' , '3'  , '1'  , to_date('02/05/2026','dd/mm/yyyy') , '5'  , '19'  );
insert into vik_reservation values ( '107' , '1'  , '2'  , to_date('09/04/2026','dd/mm/yyyy') , '16' , '55'  );
insert into vik_reservation values ( '107' , '2'  , '2'  , to_date('23/04/2026','dd/mm/yyyy') , '17' , '60'  );
insert into vik_reservation values ( '107' , '3'  , '2'  , to_date('03/05/2026','dd/mm/yyyy') , '18' , '61'  );
insert into vik_reservation values ( '108' , '1'  , '2'  , to_date('12/04/2026','dd/mm/yyyy') , '13' , '48'  );
insert into vik_reservation values ( '108' , '2'  , '2'  , to_date('24/04/2026','dd/mm/yyyy') , '14' , '49'  );
insert into vik_reservation values ( '108' , '3'  , '2'  , to_date('04/05/2026','dd/mm/yyyy') , '12' , '45'  );
insert into vik_reservation values ( '109' , '1'  , '3'  , to_date('10/04/2026','dd/mm/yyyy') , '28' , '95'  );
insert into vik_reservation values ( '109' , '2'  , '3'  , to_date('25/04/2026','dd/mm/yyyy') , '30' , '100' );
insert into vik_reservation values ( '109' , '3'  , '3'  , to_date('05/05/2026','dd/mm/yyyy') , '26' , '90'  );
insert into vik_reservation values ( '110' , '1'  , '2'  , to_date('16/04/2026','dd/mm/yyyy') , '15' , '52'  );
insert into vik_reservation values ( '110' , '2'  , '2'  , to_date('26/04/2026','dd/mm/yyyy') , '16' , '58'  );
insert into vik_reservation values ( '110' , '3'  , '2'  , to_date('06/05/2026','dd/mm/yyyy') , '14' , '50'  );

insert into vik_etape values ( '16A' , '4'  , '1'  , '27375' , '27701' , '8,5'   , to_date('08:06:00','hh24:mi:ss') );
insert into vik_etape values ( '9A'  , '5'  , '3'  , '14118' , '14456' , '22'    , to_date('12:44:00','hh24:mi:ss') );
insert into vik_etape values ( '9A'  , '6'  , '3'  , '14118' , '14431' , '38,5'  , to_date('16:32:00','hh24:mi:ss') );
insert into vik_etape values ( '9A'  , '7'  , '3'  , '14118' , '14456' , '22'    , to_date('14:15:00','hh24:mi:ss') );
insert into vik_etape values ( '19A' , '7'  , '3'  , '14456' , '14220' , '46'    , to_date('15:31:00','hh24:mi:ss') );
insert into vik_etape values ( '2B'  , '8'  , '3'  , '50129' , '50587' , '83,8'  , to_date('04:30:00','hh24:mi:ss') );
insert into vik_etape values ( '3B'  , '9'  , '5'  , '50129' , '50587' , '41'    , to_date('08:46:00','hh24:mi:ss') );
insert into vik_etape values ( '7A'  , '10' , '6'  , '14118' , '14220' , '101,2' , to_date('10:43:00','hh24:mi:ss') );
insert into vik_etape values ( '19A' , '10' , '6'  , '14220' , '14456' , '46'    , to_date('11:49:00','hh24:mi:ss') );
insert into vik_etape values ( '3B'  , '11' , '7'  , '50196' , '50587' , '68,2'  , to_date('16:02:00','hh24:mi:ss') );
insert into vik_etape values ( '2B'  , '11' , '7'  , '50587' , '14312' , '55'    , to_date('16:44:00','hh24:mi:ss') );
insert into vik_etape values ( '9A'  , '13' , '1'  , '14118' , '14456' , '22'    , to_date('12:44:00','hh24:mi:ss') );
insert into vik_etape values ( '5B'  , '16' , '3'  , '50218' , '50184' , '110,8' , to_date('07:41:00','hh24:mi:ss') );
insert into vik_etape values ( '2A'  , '16' , '3'  , '50184' , '50129' , '42,9'  , to_date('08:29:00','hh24:mi:ss') );
insert into vik_etape values ( '6A'  , '18' , '5'  , '14118' , '14174' , '57,4'  , to_date('08:27:00','hh24:mi:ss') );
insert into vik_etape values ( '12A' , '20' , '3'  , '61006' , '61214' , '54,8'  , to_date('07:24:00','hh24:mi:ss') );
insert into vik_etape values ( '2B'  , '24' , '19' , '50041' , '14118' , '200,3' , to_date('05:01:00','hh24:mi:ss') );
insert into vik_etape values ( '9A'  , '24' , '19' , '14118' , '14366' , '63,6'  , to_date('09:22:00','hh24:mi:ss') );
insert into vik_etape values ( '14A' , '24' , '19' , '14366' , '27681' , '135'   , to_date('10:38:00','hh24:mi:ss') );
insert into vik_etape values ( '18B' , '26' , '7'  , '76540' , '76351' , '96,9'  , to_date('07:40:00','hh24:mi:ss') );
insert into vik_etape values ( '12A' , '27' , '10' , '61001' , '61214' , '104'   , to_date('16:29:00','hh24:mi:ss') );
insert into vik_etape values ( '15A' , '31' , '2'  , '76540' , '76217' , '67,1'  , to_date('06:02:00','hh24:mi:ss') );
insert into vik_etape values ( '7B'  , '33' , '5'  , '76351' , '14220' , '55,2'  , to_date('10:26:00','hh24:mi:ss') );
insert into vik_etape values ( '6A'  , '35' , '8'  , '14762' , '50218' , '58,8'  , to_date('17:02:00','hh24:mi:ss') );
insert into vik_etape values ( '4B'  , '35' , '8'  , '50218' , '50147' , '28,7'  , to_date('17:49:00','hh24:mi:ss') );
insert into vik_etape values ( '12A' , '27' , '11' , '61001' , '61214' , '104'   , to_date('16:29:00','hh24:mi:ss') );
insert into vik_etape values ( '19A' , '10' , '7'  , '14220' , '14456' , '46'    , to_date('11:49:00','hh24:mi:ss') );
insert into vik_etape values ( '5B'  , '11' , '8'  , '50353' , '50639' , '44,9'  , to_date('04:06:00','hh24:mi:ss') );
insert into vik_etape values ( '6B'  , '11' , '8'  , '50639' , '14174' , '55,4'  , to_date('05:10:00','hh24:mi:ss') );
insert into vik_etape values ( '19A' , '11' , '8'  , '14174' , '61001' , '92,7'  , to_date('06:30:00','hh24:mi:ss') );
insert into vik_etape values ( '7B'  , '38' , '9'  , '76259' , '14191' , '177,6' , to_date('07:10:00','hh24:mi:ss') );
insert into vik_etape values ( '2A'  , '38' , '9'  , '14191' , '50587' , '113,8' , to_date('09:50:00','hh24:mi:ss') );
insert into vik_etape values ( '5A'  , '38' , '9'  , '50587' , '50218' , '70,9'  , to_date('11:06:00','hh24:mi:ss') );
insert into vik_etape values ( '6B'  , '38' , '9'  , '50218' , '14174' , '85'    , to_date('12:46:00','hh24:mi:ss') );
insert into vik_etape values ( '16A' , '0'  , '36' , '27375' , '27701' , '8,5'   , to_date('08:06:00','hh24:mi:ss') );
insert into vik_etape values ( '9A'  , '0'  , '37' , '14118' , '14456' , '22'    , to_date('12:44:00','hh24:mi:ss') );
insert into vik_etape values ( '9A'  , '0'  , '38' , '14118' , '14456' , '22'    , to_date('14:15:00','hh24:mi:ss') );
insert into vik_etape values ( '9A'  , '101' , '1' , '14118' , '14456' , '22'    , to_date('10:10:00','hh24:mi:ss') );
insert into vik_etape values ( '19A' , '101' , '1' , '14456' , '14220' , '46'    , to_date('11:15:00','hh24:mi:ss') );
insert into vik_etape values ( '6A'  , '101' , '2' , '14118' , '50218' , '58,8'  , to_date('09:20:00','hh24:mi:ss') );
insert into vik_etape values ( '4B'  , '101' , '2' , '50218' , '50147' , '28,7'  , to_date('10:05:00','hh24:mi:ss') );
insert into vik_etape values ( '17A' , '101' , '3' , '14118' , '27284' , '140'   , to_date('07:45:00','hh24:mi:ss') );
insert into vik_etape values ( '15A' , '101' , '3' , '27284' , '76540' , '96'    , to_date('09:10:00','hh24:mi:ss') );
insert into vik_etape values ( '11A' , '102' , '1' , '76351' , '61001' , '180'   , to_date('08:15:00','hh24:mi:ss') );
insert into vik_etape values ( '12A' , '102' , '1' , '61001' , '61214' , '104'   , to_date('10:40:00','hh24:mi:ss') );
insert into vik_etape values ( '13A' , '102' , '2' , '14366' , '76540' , '120'   , to_date('07:50:00','hh24:mi:ss') );
insert into vik_etape values ( '18B' , '102' , '2' , '76540' , '76351' , '96,9'  , to_date('09:35:00','hh24:mi:ss') );
insert into vik_etape values ( '15A' , '102' , '3' , '27284' , '76540' , '90'    , to_date('14:10:00','hh24:mi:ss') );
insert into vik_etape values ( '13B' , '102' , '3' , '76540' , '14366' , '118'   , to_date('16:05:00','hh24:mi:ss') );
insert into vik_etape values ( '6A'  , '103' , '1' , '14118' , '50218' , '58,8'  , to_date('08:05:00','hh24:mi:ss') );
insert into vik_etape values ( '3B'  , '103' , '1' , '50218' , '14118' , '68,2'  , to_date('09:40:00','hh24:mi:ss') );
insert into vik_etape values ( '4A'  , '103' , '2' , '14118' , '14762' , '38'    , to_date('12:15:00','hh24:mi:ss') );
insert into vik_etape values ( '6B'  , '103' , '2' , '14762' , '14118' , '58,8'  , to_date('13:35:00','hh24:mi:ss') );
insert into vik_etape values ( '9A'  , '103' , '3' , '14118' , '14366' , '63,6'  , to_date('10:30:00','hh24:mi:ss') );
insert into vik_etape values ( '13A' , '103' , '3' , '14366' , '76540' , '120'   , to_date('12:10:00','hh24:mi:ss') );
insert into vik_etape values ( '4A'  , '104' , '1' , '14118' , '14762' , '38'    , to_date('07:40:00','hh24:mi:ss') );
insert into vik_etape values ( '10A' , '104' , '1' , '14762' , '61700' , '70'    , to_date('09:00:00','hh24:mi:ss') );
insert into vik_etape values ( '12A' , '104' , '2' , '61006' , '61214' , '54,8'  , to_date('13:15:00','hh24:mi:ss') );
insert into vik_etape values ( '19B' , '104' , '2' , '61001' , '14220' , '100'   , to_date('15:20:00','hh24:mi:ss') );
insert into vik_etape values ( '10A' , '104' , '3' , '14118' , '61700' , '90'    , to_date('16:00:00','hh24:mi:ss') );
insert into vik_etape values ( '12B' , '104' , '3' , '61001' , '28280' , '110'   , to_date('18:20:00','hh24:mi:ss') );
insert into vik_etape values ( '5A'  , '105' , '1' , '50184' , '50353' , '110,8' , to_date('08:00:00','hh24:mi:ss') );
insert into vik_etape values ( '8B'  , '105' , '1' , '50353' , '14118' , '105'   , to_date('09:45:00','hh24:mi:ss') );
insert into vik_etape values ( '6A'  , '105' , '2' , '14118' , '50218' , '58,8'  , to_date('12:20:00','hh24:mi:ss') );
insert into vik_etape values ( '5B'  , '105' , '2' , '50218' , '50184' , '110,8' , to_date('14:05:00','hh24:mi:ss') );
insert into vik_etape values ( '3A'  , '105' , '3' , '14118' , '50196' , '150'   , to_date('15:10:00','hh24:mi:ss') );
insert into vik_etape values ( '3B'  , '105' , '3' , '50196' , '14118' , '140'   , to_date('17:30:00','hh24:mi:ss') );
insert into vik_etape values ( '4A'  , '106' , '1' , '14118' , '14762' , '38'    , to_date('07:15:00','hh24:mi:ss') );
insert into vik_etape values ( '10A' , '106' , '1' , '14762' , '61700' , '70'    , to_date('08:40:00','hh24:mi:ss') );
insert into vik_etape values ( '7A'  , '106' , '2' , '14118' , '80373' , '101,2' , to_date('11:30:00','hh24:mi:ss') );
insert into vik_etape values ( '7B'  , '106' , '2' , '80373' , '14118' , '101,2' , to_date('13:10:00','hh24:mi:ss') );
insert into vik_etape values ( '1A'  , '106' , '3' , '14118' , '50041' , '200'   , to_date('14:50:00','hh24:mi:ss') );
insert into vik_etape values ( '1B'  , '106' , '3' , '50041' , '14118' , '200'   , to_date('18:20:00','hh24:mi:ss') );
insert into vik_etape values ( '6A'  , '107' , '1' , '14118' , '50218' , '58,8'  , to_date('09:10:00','hh24:mi:ss') );
insert into vik_etape values ( '3B'  , '107' , '1' , '50218' , '14118' , '68,2'  , to_date('10:50:00','hh24:mi:ss') );
insert into vik_etape values ( '8A'  , '107' , '2' , '14118' , '50353' , '130'   , to_date('13:15:00','hh24:mi:ss') );
insert into vik_etape values ( '5B'  , '107' , '2' , '50353' , '50184' , '44,9'  , to_date('15:00:00','hh24:mi:ss') );
insert into vik_etape values ( '9A'  , '107' , '3' , '14118' , '14366' , '63,6'  , to_date('16:25:00','hh24:mi:ss') );
insert into vik_etape values ( '13A' , '107' , '3' , '14366' , '76540' , '120'   , to_date('18:05:00','hh24:mi:ss') );
insert into vik_etape values ( '12A' , '108' , '1' , '61006' , '61214' , '54,8'  , to_date('08:00:00','hh24:mi:ss') );
insert into vik_etape values ( '19B' , '108' , '1' , '61001' , '14220' , '100'   , to_date('10:00:00','hh24:mi:ss') );
insert into vik_etape values ( '17A' , '108' , '2' , '14118' , '27284' , '140'   , to_date('11:40:00','hh24:mi:ss') );
insert into vik_etape values ( '15A' , '108' , '2' , '27284' , '76540' , '96'    , to_date('13:30:00','hh24:mi:ss') );
insert into vik_etape values ( '13B' , '108' , '3' , '76540' , '14366' , '118'   , to_date('15:00:00','hh24:mi:ss') );
insert into vik_etape values ( '9B'  , '108' , '3' , '14366' , '14118' , '63,6'  , to_date('16:30:00','hh24:mi:ss') );
insert into vik_etape values ( '2A'  , '109' , '1' , '14118' , '50129' , '120'   , to_date('07:50:00','hh24:mi:ss') );
insert into vik_etape values ( '3B'  , '109' , '1' , '50129' , '14118' , '140'   , to_date('09:40:00','hh24:mi:ss') );
insert into vik_etape values ( '1A'  , '109' , '2' , '14118' , '50041' , '200'   , to_date('11:10:00','hh24:mi:ss') );
insert into vik_etape values ( '2B'  , '109' , '2' , '50041' , '14118' , '200'   , to_date('14:45:00','hh24:mi:ss') );
insert into vik_etape values ( '8A'  , '109' , '3' , '14118' , '50353' , '130'   , to_date('15:50:00','hh24:mi:ss') );
insert into vik_etape values ( '5B'  , '109' , '3' , '50353' , '50184' , '44,9'  , to_date('17:20:00','hh24:mi:ss') );
insert into vik_etape values ( '19A' , '110' , '1' , '14220' , '61001' , '100'   , to_date('07:40:00','hh24:mi:ss') );
insert into vik_etape values ( '12B' , '110' , '1' , '61001' , '28280' , '110'   , to_date('09:50:00','hh24:mi:ss') );
insert into vik_etape values ( '13A' , '110' , '2' , '14366' , '76540' , '120'   , to_date('12:10:00','hh24:mi:ss') );
insert into vik_etape values ( '18B' , '110' , '2' , '76540' , '76351' , '96,9'  , to_date('14:00:00','hh24:mi:ss') );
insert into vik_etape values ( '11A' , '110' , '3' , '76351' , '61001' , '180'   , to_date('15:30:00','hh24:mi:ss') );
insert into vik_etape values ( '12A' , '110' , '3' , '61001' , '61214' , '104'   , to_date('18:00:00','hh24:mi:ss') );

commit;

/*
LISTE DES REQUETES
*/

select * from vik_type_client;
select * from vik_client;
select * from vik_departement;
select * from vik_reduction;
select * from vik_tarif;
select * from vik_commune;
select * from vik_ligne;
select * from vik_reservation order by cli_num, res_num;
select * from vik_etape order by cli_num, res_num ;
SELECT lig_num,cli_num,res_num,com_code_insee_depart,com_code_insee_arrivee,eta_distance,
to_char(eta_heure,'hh24:mi:ss') 
FROM vik_etape
order by cli_num, res_num ;