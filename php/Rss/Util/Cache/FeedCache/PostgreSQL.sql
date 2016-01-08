--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;

SET search_path = public, pg_catalog;

DROP TRIGGER old_rows_gc ON public.cache;
ALTER TABLE ONLY public.cache DROP CONSTRAINT cache_pkey;
DROP TABLE public.cache;
DROP SEQUENCE public.cacheid;
DROP FUNCTION public.delete_old_rows();
DROP SCHEMA public;
--
-- Name: public; Type: SCHEMA; Schema: -; Owner: ivyieiunipuhph
--

CREATE SCHEMA public;


ALTER SCHEMA public OWNER TO ivyieiunipuhph;

--
-- Name: SCHEMA public; Type: COMMENT; Schema: -; Owner: ivyieiunipuhph
--

COMMENT ON SCHEMA public IS 'standard public schema';


SET search_path = public, pg_catalog;

--
-- Name: delete_old_rows(); Type: FUNCTION; Schema: public; Owner: ivyieiunipuhph
--

CREATE FUNCTION delete_old_rows() RETURNS trigger
    LANGUAGE plpgsql
    AS $$ BEGIN DELETE FROM cache WHERE foundtimestamp < NOW() - INTERVAL '30 days'; RETURN NEW; END; $$;


ALTER FUNCTION public.delete_old_rows() OWNER TO ivyieiunipuhph;

--
-- Name: cacheid; Type: SEQUENCE; Schema: public; Owner: ivyieiunipuhph
--

CREATE SEQUENCE cacheid
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.cacheid OWNER TO ivyieiunipuhph;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: cache; Type: TABLE; Schema: public; Owner: ivyieiunipuhph; Tablespace: 
--

CREATE TABLE cache (
    id integer DEFAULT nextval('cacheid'::regclass) NOT NULL,
    title character varying(1024),
    link character varying(1024),
    stemmed character varying(1024),
    metaphone character varying(255),
    foundtimestamp timestamp without time zone DEFAULT now()
);


ALTER TABLE public.cache OWNER TO ivyieiunipuhph;

--
-- Name: cache_pkey; Type: CONSTRAINT; Schema: public; Owner: ivyieiunipuhph; Tablespace: 
--

ALTER TABLE ONLY cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (id);


--
-- Name: old_rows_gc; Type: TRIGGER; Schema: public; Owner: ivyieiunipuhph
--

CREATE TRIGGER old_rows_gc AFTER INSERT ON cache FOR EACH STATEMENT EXECUTE PROCEDURE delete_old_rows();


--
-- Name: public; Type: ACL; Schema: -; Owner: ivyieiunipuhph
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM ivyieiunipuhph;
GRANT ALL ON SCHEMA public TO ivyieiunipuhph;
GRANT ALL ON SCHEMA public TO PUBLIC;


--
-- PostgreSQL database dump complete
--

