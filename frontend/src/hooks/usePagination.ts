'use client';

import { useState, useCallback, useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import type { PaginatedResponse } from '@/types/api';

interface UsePaginationOptions {
  endpoint: string;
  perPage?: number;
  queryKey: string[];
  params?: Record<string, string | number | boolean | undefined>;
  enabled?: boolean;
}

/**
 * Pagination hook for API list endpoints that return Laravel-style pagination
 */
export function usePagination<T>({
  endpoint,
  perPage = 20,
  queryKey,
  params = {},
  enabled = true,
}: UsePaginationOptions) {
  const [page, setPage] = useState(1);

  const queryParams = useMemo(() => {
    const p: Record<string, string> = {
      page: String(page),
      per_page: String(perPage),
    };
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== '') {
        p[key] = String(value);
      }
    });
    return p;
  }, [page, perPage, params]);

  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: [...queryKey, queryParams],
    queryFn: async () => {
      const response = await api.get<PaginatedResponse<T>>(endpoint, {
        params: queryParams,
      });
      return response.data;
    },
    enabled,
  });

  const goToPage = useCallback((newPage: number) => {
    setPage(newPage);
  }, []);

  const nextPage = useCallback(() => {
    if (data && page < data.meta.last_page) {
      setPage((p) => p + 1);
    }
  }, [data, page]);

  const prevPage = useCallback(() => {
    if (page > 1) {
      setPage((p) => p - 1);
    }
  }, [page]);

  return {
    data: data?.data ?? [],
    meta: data?.meta,
    links: data?.links,
    page,
    isLoading,
    isError,
    error,
    goToPage,
    nextPage,
    prevPage,
    refetch,
    hasNextPage: data ? page < data.meta.last_page : false,
    hasPrevPage: page > 1,
  };
}
