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
      const response = await api.get(endpoint, {
        params: queryParams,
      });
      const raw = response.data?.data ?? response.data;
      // Laravel paginate() wraps in { data: [...], current_page, last_page, ... }
      // Our API wraps further in { success, data: { data, current_page, ... } }
      if (raw && Array.isArray(raw.data) && typeof raw.last_page === 'number') {
        return {
          data: raw.data as T[],
          meta: {
            current_page: raw.current_page ?? 1,
            from: raw.from ?? null,
            last_page: raw.last_page ?? 1,
            per_page: raw.per_page ?? perPage,
            to: raw.to ?? null,
            total: raw.total ?? 0,
          },
          links: raw.links ?? {},
        } as PaginatedResponse<T>;
      }
      // Fallback: non-paginated array
      const items = Array.isArray(raw) ? raw : [];
      return {
        data: items as T[],
        meta: { current_page: 1, from: 1, last_page: 1, per_page: items.length, to: items.length, total: items.length },
        links: {},
      } as PaginatedResponse<T>;
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
