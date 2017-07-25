<?php

/**
 * Class LP_Course_CURD
 *
 * @author  ThimPress
 * @package LearnPress/Classes/CURD
 * @since   3.x.x
 */
class LP_Course_CURD implements LP_Interface_CURD {

	/**
	 * Load course data
	 *
	 * @param LP_Course $course
	 *
	 * @return mixed
	 */
	public function load( &$course ) {
		$this->load_curriculum( $course );

		return $course;
	}

	public function update() {
		// TODO: Implement update() method.
	}

	public function delete() {
		// TODO: Implement delete() method.
	}

	/**
	 * Load course curriculum.
	 *
	 * @param LP_Course $course
	 */
	protected function load_curriculum( &$course ) {
		$course_id = $course->get_id();
		$this->read_course_curriculum( $course_id );
	}

	/**
	 * Read curriculum of a course from db into cache.
	 *
	 * @param $course_id
	 *
	 * @return bool
	 */
	public function read_course_curriculum( $course_id ) {

		global $wpdb;

		if ( is_numeric( $course_id ) ) {
			settype( $course_id, 'array' );
		}

		$fetch_ids = array();

		/**
		 * Get course's data from cache and if it is already existed
		 * then ignore that course.
		 */
		foreach ( $course_id as $id ) {
			if ( false === wp_cache_get( 'course-' . $id, 'lp-course-curriculum' ) ) {
				$fetch_ids[] = $id;
			}
		}

		// There is no course ids to read
		if ( ! $fetch_ids ) {
			return false;
		}

		// Read course sections
		$this->read_course_sections( $fetch_ids );

		$all_section_ids = array();
		foreach ( $fetch_ids as $id ) {
			if ( $sections = $this->get_course_sections( $id ) ) {
				$section_ids     = wp_list_pluck( $sections, 'section_id' );
				$all_section_ids = array_merge( $all_section_ids, $section_ids );
			}

			// Set cache
			wp_cache_set( 'course-' . $id, array(), 'lp-course-curriculum' );
		}

		if ( $all_section_ids ) {
			$format = array_fill( 0, sizeof( $all_section_ids ), '%d' );
			$query  = $wpdb->prepare( "
				SELECT s.*, si.*
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->learnpress_section_items} si ON si.item_id = p.ID
				INNER JOIN {$wpdb->learnpress_sections} s ON s.section_id = si.section_id
				WHERE s.section_id IN(" . join( ',', $format ) . ")
				ORDER BY s.section_course_id, s.section_order, si.item_order ASC
			", $all_section_ids );

			if ( $results = $wpdb->get_results( $query ) ) {
				$curriculum = array();
				$cur_id     = 0;
				foreach ( $results as $row ) {

					// Switch to other course
					if ( $row->section_course_id !== $cur_id ) {

						// If $cur_id is already set to a course
						if ( $cur_id ) {
							wp_cache_replace( 'course-' . $cur_id, $curriculum, 'lp-course-curriculum' );
						}

						// Set $cur_id to new course and reset $curriculum
						$cur_id     = $row->section_course_id;
						$curriculum = array();
					}

					$curriculum[] = $row;
				}
				wp_cache_replace( 'course-' . $cur_id, $curriculum, 'lp-course-curriculum' );
				unset( $curriculum );
			}
		}

		return true;
	}

	/**
	 * Get sections of course
	 *
	 * @param $course_id
	 *
	 * @return array
	 */
	public function get_course_sections( $course_id ) {
		$this->read_course_sections( $course_id );

		return wp_cache_get( 'course-' . $course_id, 'lp-course-sections' );
	}

	/**
	 * Read sections of a bundle of courses by ids
	 *
	 * @param int|array $course_id
	 *
	 * @return mixed|array
	 */
	public function read_course_sections( $course_id ) {
		global $wpdb;

		if ( is_numeric( $course_id ) ) {
			settype( $course_id, 'array' );
		}

		$fetch_ids = array();

		/**
		 * Get course's data from cache and if it is already existed
		 * then ignore that course.
		 */
		foreach ( $course_id as $id ) {
			if ( false === wp_cache_get( 'course-' . $id, 'lp-course-sections' ) ) {
				$fetch_ids[] = $id;
			}
		}

		// There is no course ids to read
		if ( ! $fetch_ids ) {
			return false;
		}

		$format = array_fill( 0, sizeof( $fetch_ids ), '%d' );
		$query  = $wpdb->prepare( "
			SELECT s.*
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->learnpress_sections} s ON p.ID = s.section_course_id
			WHERE p.ID IN(" . join( ',', $format ) . ")
			ORDER BY p.ID, `section_order` ASC
		", $fetch_ids );

		if ( $results = $wpdb->get_results( $query ) ) {
			$course_sections = array();
			$cur_id          = 0;
			foreach ( $results as $row ) {
				if ( $row->section_course_id !== $cur_id ) {
					if ( $cur_id ) {
						wp_cache_set( 'course-' . $cur_id, $course_sections, 'lp-course-sections' );
					}
					$cur_id          = $row->section_course_id;
					$course_sections = array();
				}
				$course_sections[] = $row;
			}
			wp_cache_set( 'course-' . $cur_id, $course_sections, 'lp-course-sections' );
			unset( $course_sections );
		}

		return true;
	}
}